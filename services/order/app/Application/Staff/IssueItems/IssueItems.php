<?php

declare(strict_types=1);

namespace App\Application\Staff\IssueItems;

use App\Application\Exceptions\OrderNotFound;
use App\Application\Ports\AuthenticatedUser;
use App\Application\Ports\Clock;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\TransactionRunnerInterface;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Domain\Order\Order;
use App\Domain\Order\OrderStatus;
use App\Enums\Role;

final readonly class IssueItems
{
    public function __construct(
        private TransactionRunnerInterface $transactions,
        private OrderRepository $orders,
        private Clock $clock,
    ) {
    }

    /**
     * @param list<string> $itemIds
     */
    public function execute(AuthenticatedUser $user, string $orderId, array $itemIds): IssueItemsResult
    {
        return $this->transactions->run(function () use ($user, $orderId, $itemIds): IssueItemsResult {
            $order = $this->orders->findByIdForUpdate($orderId);

            if ($order === null || ! $this->canIssue($user, $order)) {
                throw new OrderNotFound($orderId);
            }

            if (! in_array($order->status(), [OrderStatus::PAID, OrderStatus::IN_RENTAL], true)) {
                throw new InvalidOrderTransition($order->status(), OrderStatus::IN_RENTAL);
            }

            $result = $order->issueItems($itemIds, $this->clock->now(), $user->id());

            if ($result->processedItemIds !== []) {
                $this->orders->save($order);
            }

            return new IssueItemsResult(
                order: $order,
                processedItemIds: $result->processedItemIds,
                skippedItemIds: $result->skippedItemIds,
            );
        });
    }

    private function canIssue(AuthenticatedUser $user, Order $order): bool
    {
        return match ($user->role()) {
            Role::STORE_MANAGER => $user->storeId() !== null && $order->storeId() === $user->storeId(),
            Role::ADMIN => true,
            default => false,
        };
    }
}
