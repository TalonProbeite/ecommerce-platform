<?php

declare(strict_types=1);

namespace App\Application\Payments\Webhooks;

use App\Application\Exceptions\PaymentNotFound;
use App\Application\Exceptions\RetryableException;
use App\Application\Payments\PaymentRecord;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\RefundRecord;
use App\Application\Payments\RefundStatus;
use App\Application\Payments\StripeEvent;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\PaymentGateway;
use App\Application\Ports\PaymentRepository;
use App\Application\Ports\TransactionRunnerInterface;
use App\Domain\Order\Order;
use App\Domain\Order\OrderStatus;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

final readonly class OnPaymentSucceeded
{
    public function __construct(
        private TransactionRunnerInterface $transactions,
        private OrderRepository $orders,
        private PaymentRepository $payments,
        private PaymentGateway $gateway,
        private IdGenerator $ids,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(StripeEvent $event, CarbonImmutable $now): void
    {
        $this->transactions->run(function () use ($event, $now): void {
            $providerPaymentId = $this->providerPaymentId($event);
            $payment = $this->payments->findByProviderPaymentIdForUpdate($providerPaymentId);

            if ($payment === null) {
                throw new PaymentNotFound($providerPaymentId);
            }

            if (! $this->matchesPaymentIntent($event, $payment)) {
                $this->logger->critical('Stripe payment amount or currency mismatch; manual review required.', [
                    'event_id' => $event->eventId,
                    'payment_id' => $payment->id,
                    'provider_payment_id' => $payment->providerPaymentId,
                    'expected_amount' => $payment->amount,
                    'amount_received' => $event->data['amount_received'] ?? null,
                    'expected_currency' => 'eur',
                    'event_currency' => $event->data['currency'] ?? null,
                ]);

                return;
            }

            $this->payments->updateStatus($payment->id, PaymentStatus::SUCCEEDED, $now, $now);

            $order = $this->orders->findByIdForUpdate($payment->orderId);

            if ($order === null) {
                throw new RetryableException(sprintf(
                    'Order "%s" for payment "%s" does not exist.',
                    $payment->orderId,
                    $payment->providerPaymentId,
                ));
            }

            match ($order->status()) {
                OrderStatus::PAYMENT_PENDING => $this->markOrderPaid($order, $now),
                OrderStatus::CANCELLED => $this->refundCancelledOrder($order, $payment, $now),
                OrderStatus::PAID => $this->logAlreadyPaid($order, $event),
                default => throw new RetryableException(sprintf(
                    'Payment "%s" succeeded for an order "%s" in an unexpected status "%s".',
                    $payment->providerPaymentId,
                    $order->id(),
                    $order->status()->value,
                )),
            };
        });
    }

    private function markOrderPaid(Order $order, CarbonImmutable $now): void
    {
        $order->markPaid($now);
        $this->orders->save($order);
    }

    private function refundCancelledOrder(Order $order, PaymentRecord $payment, CarbonImmutable $now): void
    {
        if ($this->payments->hasRefundWithReason(
            $order->id(),
            'order_cancelled',
            [RefundStatus::PENDING, RefundStatus::SUCCEEDED],
        )) {
            return;
        }

        $refundId = $this->ids->next();

        $this->payments->insertRefund(new RefundRecord(
            id: $refundId,
            orderId: $order->id(),
            orderItemId: null,
            paymentId: $payment->id,
            providerRefundId: null,
            amount: $payment->amount,
            reason: 'order_cancelled',
            status: RefundStatus::PENDING,
            createdAt: $now,
            updatedAt: $now,
        ));

        $refund = $this->gateway->refund(
            $payment->providerPaymentId,
            $payment->amount,
            sprintf('order:%s:refund:all', $order->id()),
        );

        $status = RefundStatus::isAllowed($refund->status) ? $refund->status : RefundStatus::PENDING;

        $this->payments->updateRefund($refundId, $status, $refund->providerId, $now);
    }

    private function logAlreadyPaid(Order $order, StripeEvent $event): void
    {
        $this->logger->info('Ignoring a successful payment for an already paid order.', [
            'order_id' => $order->id(),
            'event_id' => $event->eventId,
        ]);
    }

    private function matchesPaymentIntent(StripeEvent $event, PaymentRecord $payment): bool
    {
        $amountReceived = $event->data['amount_received'] ?? null;
        $currency = $event->data['currency'] ?? null;

        return is_int($amountReceived)
            && $amountReceived === $payment->amount
            && is_string($currency)
            && strtolower($currency) === 'eur';
    }

    private function providerPaymentId(StripeEvent $event): string
    {
        $providerPaymentId = $event->data['id'] ?? null;

        if (! is_string($providerPaymentId) || $providerPaymentId === '') {
            throw new RetryableException(sprintf(
                'Stripe event "%s" does not contain a payment id.',
                $event->eventId,
            ));
        }

        return $providerPaymentId;
    }
}
