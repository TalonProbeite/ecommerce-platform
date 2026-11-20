<?php

declare(strict_types=1);

namespace App\Application\Staff\ReturnItems;

use App\Application\Exceptions\OrderNotFound;
use App\Application\Exceptions\RefundExceedsPayment;
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
use App\Domain\Order\Order;
use App\Domain\Order\OrderStatus;
use App\Enums\Role;
use Carbon\CarbonImmutable;

final readonly class ReturnItems
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

    /**
     * @param list<string> $itemIds
     */
    public function execute(AuthenticatedUser $user, string $orderId, array $itemIds): ReturnItemsResult
    {
        return $this->transactions->run(function () use ($user, $orderId, $itemIds): ReturnItemsResult {
            $order = $this->orders->findByIdForUpdate($orderId);

            if ($order === null || ! $this->canReturn($user, $order)) {
                throw new OrderNotFound($orderId);
            }

            if ($order->status() !== OrderStatus::IN_RENTAL) {
                throw new InvalidOrderTransition($order->status(), OrderStatus::COMPLETED);
            }

            $now = $this->clock->now();
            $result = $order->returnItems($itemIds, $now, $user->id());

            if ($result->processedItemIds !== []) {
                $this->refundDeposits($order, $result->processedItemIds, $now);
                $this->orders->save($order);
            }

            return new ReturnItemsResult(
                order: $order,
                processedItemIds: $result->processedItemIds,
                skippedItemIds: $result->skippedItemIds,
            );
        });
    }

    /**
     * @param list<string> $itemIds
     */
    private function refundDeposits(Order $order, array $itemIds, CarbonImmutable $now): void
    {
        $items = [];

        foreach ($order->items() as $item) {
            $items[$item->lineId()] = $item;
        }

        foreach ($itemIds as $itemId) {
            $item = $items[$itemId];
            $amount = $item->depositTotal()->amount();

            if ($amount <= 0) {
                continue;
            }

            $this->assertWithinPayment($order, $amount);

            $payment = $this->payments->findLatestSucceededByOrderId($order->id());

            if ($payment === null) {
                throw new RefundExceedsPayment($order->id(), $amount, 0);
            }

            $refundId = $this->ids->next();

            $this->payments->insertRefund(new RefundRecord(
                id: $refundId,
                orderId: $order->id(),
                orderItemId: $itemId,
                paymentId: $payment->id,
                providerRefundId: null,
                amount: $amount,
                reason: 'item_returned',
                status: RefundStatus::PENDING,
                createdAt: $now,
                updatedAt: $now,
            ));

            $refund = $this->gateway->refund(
                $payment->providerPaymentId,
                $amount,
                sprintf('order:%s:refund:item:%s', $order->id(), $itemId),
            );

            $status = RefundStatus::isAllowed($refund->status) ? $refund->status : RefundStatus::PENDING;

            $this->payments->updateRefund($refundId, $status, $refund->providerId, $now);
        }
    }

    private function assertWithinPayment(Order $order, int $amount): void
    {
        $succeeded = $this->payments->succeededTotalByOrderId($order->id());
        $refunded = $this->payments->refundedTotalByOrderId($order->id());

        if ($refunded + $amount > $succeeded) {
            throw new RefundExceedsPayment(
                $order->id(),
                $amount,
                max(0, $succeeded - $refunded),
            );
        }
    }

    private function canReturn(AuthenticatedUser $user, Order $order): bool
    {
        return match ($user->role()) {
            Role::STORE_MANAGER => $user->storeId() !== null && $order->storeId() === $user->storeId(),
            Role::ADMIN => true,
            default => false,
        };
    }
}
