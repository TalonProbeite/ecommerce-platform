<?php

declare(strict_types=1);

namespace App\Application\Customer\RequestPaymentIntent;

use App\Application\Exceptions\OrderNotFound;
use App\Application\Payments\PaymentIntentRequest;
use App\Application\Payments\PaymentRecord;
use App\Application\Ports\AuthenticatedUser;
use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\PaymentGateway;
use App\Application\Ports\PaymentRepository;
use App\Application\Ports\TransactionRunnerInterface;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Domain\Money\Money;
use App\Domain\Order\OrderStatus;

final readonly class RequestPaymentIntent
{
    public function __construct(
        private TransactionRunnerInterface $transactions,
        private OrderRepository $orders,
        private PaymentRepository $payments,
        private PaymentGateway $gateway,
        private Clock $clock,
        private IdGenerator $ids,
    ) {
    }

    public function execute(AuthenticatedUser $user, string $orderId): RequestPaymentIntentResult
    {
        return $this->transactions->run(function () use ($user, $orderId): RequestPaymentIntentResult {
            $order = $this->orders->findByIdForUpdate($orderId);

            if ($order === null || $order->customerId() !== $user->id()) {
                throw new OrderNotFound($orderId);
            }

            $status = $order->status();

            if ($status !== OrderStatus::STOCK_RESERVED && $status !== OrderStatus::PAYMENT_PENDING) {
                throw new InvalidOrderTransition($status, OrderStatus::PAYMENT_PENDING);
            }

            $openPayment = $this->payments->findOpenByOrderId($orderId);

            if ($openPayment !== null) {
                $intent = $this->gateway->retrieveIntent($openPayment->providerPaymentId);

                return new RequestPaymentIntentResult(
                    clientSecret: $intent->clientSecret ?? '',
                    status: $intent->status,
                    amountTotal: $order->total()->amount(),
                    currency: Money::CURRENCY,
                    created: false,
                );
            }

            $attemptNo = $this->payments->countByOrderId($orderId) + 1;
            $amount = $order->total()->amount();
            $now = $this->clock->now();

            $intent = $this->gateway->createIntent(
                new PaymentIntentRequest(
                    orderId: $order->id(),
                    amount: $amount,
                    currency: $this->currency(),
                ),
                sprintf('order:%s:pi:%d', $order->id(), $attemptNo),
            );

            $this->payments->insert(new PaymentRecord(
                id: $this->ids->next(),
                orderId: $order->id(),
                provider: 'stripe',
                providerPaymentId: $intent->providerId,
                amount: $amount,
                currency: Money::CURRENCY,
                status: $intent->status,
                failureCode: null,
                succeededAt: null,
                createdAt: $now,
                updatedAt: $now,
            ));

            if ($order->status() === OrderStatus::STOCK_RESERVED) {
                $order->markPaymentPending($now);
                $this->orders->save($order);
            }

            return new RequestPaymentIntentResult(
                clientSecret: $intent->clientSecret ?? '',
                status: $intent->status,
                amountTotal: $amount,
                currency: Money::CURRENCY,
                created: true,
            );
        });
    }

    private function currency(): string
    {
        $currency = config('stripe.currency', 'eur');

        return is_string($currency) && $currency !== '' ? strtolower($currency) : 'eur';
    }
}
