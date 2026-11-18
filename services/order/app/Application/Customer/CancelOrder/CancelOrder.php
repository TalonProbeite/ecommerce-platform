<?php

declare(strict_types=1);

namespace App\Application\Customer\CancelOrder;

use App\Application\Exceptions\OrderNotFound;
use App\Application\Exceptions\PaymentInProgress;
use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Payments\PaymentRecord;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\ProviderPaymentIntent;
use App\Application\Payments\RefundRecord;
use App\Application\Payments\RefundStatus;
use App\Application\Ports\AuthenticatedUser;
use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\PaymentGateway;
use App\Application\Ports\PaymentRepository;
use App\Application\Ports\TransactionRunnerInterface;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Domain\Order\ActorRole;
use App\Domain\Order\CancelReason;
use App\Domain\Order\Order;
use App\Domain\Order\OrderStatus;
use App\Enums\Role;

final readonly class CancelOrder
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

    public function execute(
        AuthenticatedUser $user,
        string $orderId,
        CancelReason $reason,
        bool $onlyIfUnpaid = false,
    ): CancelOrderResult {
        return $this->transactions->run(function () use ($user, $orderId, $reason, $onlyIfUnpaid): CancelOrderResult {
            $order = $this->orders->findByIdForUpdate($orderId);

            if ($order === null || ! $this->canCancel($user, $order)) {
                throw new OrderNotFound($orderId);
            }

            return $this->cancel(
                $order,
                $reason,
                ActorRole::fromRole($user->role()),
                $onlyIfUnpaid,
            );
        });
    }

    public function executeAsSystem(string $orderId, CancelReason $reason, bool $onlyIfUnpaid = false): CancelOrderResult
    {
        return $this->transactions->run(function () use ($orderId, $reason, $onlyIfUnpaid): CancelOrderResult {
            $order = $this->orders->findByIdForUpdate($orderId);

            if ($order === null) {
                throw new OrderNotFound($orderId);
            }

            return $this->cancel($order, $reason, ActorRole::SYSTEM, $onlyIfUnpaid);
        });
    }

    private function cancel(
        Order $order,
        CancelReason $reason,
        ActorRole $actorRole,
        bool $onlyIfUnpaid,
    ): CancelOrderResult {
        $status = $order->status();

        if ($status === OrderStatus::CANCELLED) {
            return new CancelOrderResult($order, false);
        }

        if ($status === OrderStatus::IN_RENTAL || $status === OrderStatus::COMPLETED) {
            throw new InvalidOrderTransition($status, OrderStatus::CANCELLED);
        }

        if ($onlyIfUnpaid && $status === OrderStatus::PAID) {
            return new CancelOrderResult($order, false);
        }

        $now = $this->clock->now();
        $openPayment = $this->payments->findOpenByOrderId($order->id());
        $paymentSucceeded = false;

        if ($openPayment !== null) {
            $intent = $this->gateway->cancelIntent(
                $openPayment->providerPaymentId,
                sprintf('order:%s:cancel-pi:%s', $order->id(), $openPayment->providerPaymentId),
            );

            $paymentSucceeded = $this->applyCancellationStatus($openPayment, $intent, $now);
        }

        $order->cancel($reason, $actorRole, $now);

        if ($status === OrderStatus::PAID || $paymentSucceeded) {
            $this->refundAll($order, $now);
        }

        $this->orders->save($order);

        return new CancelOrderResult($order, true);
    }

    private function applyCancellationStatus(
        PaymentRecord $payment,
        ProviderPaymentIntent $intent,
        \Carbon\CarbonImmutable $now,
    ): bool {
        return match ($intent->status) {
            PaymentStatus::CANCELED => $this->cancelPayment($payment, $now),
            PaymentStatus::SUCCEEDED => $this->succeedPayment($payment, $now),
            PaymentStatus::PROCESSING => throw new PaymentInProgress(
                $payment->orderId,
                $payment->providerPaymentId,
            ),
            default => throw new PaymentProviderUnavailable(sprintf(
                'Stripe PaymentIntent "%s" could not be canceled in status "%s".',
                $payment->providerPaymentId,
                $intent->status,
            )),
        };
    }

    private function cancelPayment(PaymentRecord $payment, \Carbon\CarbonImmutable $now): bool
    {
        $this->payments->updateStatus($payment->id, PaymentStatus::CANCELED, $now);

        return false;
    }

    private function succeedPayment(PaymentRecord $payment, \Carbon\CarbonImmutable $now): bool
    {
        $this->payments->updateStatus($payment->id, PaymentStatus::SUCCEEDED, $now, $now);

        return true;
    }

    private function refundAll(Order $order, \Carbon\CarbonImmutable $now): void
    {
        if ($this->payments->hasRefundWithReason(
            $order->id(),
            'order_cancelled',
            [RefundStatus::PENDING, RefundStatus::SUCCEEDED],
        )) {
            return;
        }

        $remaining = $this->payments->succeededTotalByOrderId($order->id())
            - $this->payments->refundedTotalByOrderId($order->id());

        if ($remaining <= 0) {
            return;
        }

        $payment = $this->payments->findLatestSucceededByOrderId($order->id());

        if ($payment === null) {
            return;
        }

        $refundId = $this->ids->next();

        $this->payments->insertRefund(new RefundRecord(
            id: $refundId,
            orderId: $order->id(),
            orderItemId: null,
            paymentId: $payment->id,
            providerRefundId: null,
            amount: $remaining,
            reason: 'order_cancelled',
            status: RefundStatus::PENDING,
            createdAt: $now,
            updatedAt: $now,
        ));

        $refund = $this->gateway->refund(
            $payment->providerPaymentId,
            $remaining,
            sprintf('order:%s:refund:all', $order->id()),
        );

        $status = RefundStatus::isAllowed($refund->status) ? $refund->status : RefundStatus::PENDING;

        $this->payments->updateRefund($refundId, $status, $refund->providerId, $now);
    }

    private function canCancel(AuthenticatedUser $user, Order $order): bool
    {
        return match ($user->role()) {
            Role::CUSTOMER => $order->customerId() === $user->id(),
            Role::STORE_MANAGER => $user->storeId() !== null && $order->storeId() === $user->storeId(),
            Role::ADMIN => true,
            Role::ANALYST => false,
        };
    }
}
