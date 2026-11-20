<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\OrderReadRepository;
use App\Application\Query\ListOrders\OrderListCriteria;
use App\Application\Query\ListOrders\OrderListResult;
use App\Domain\Order\Order;

final class InMemoryOrderReadRepository implements OrderReadRepository
{
    /**
     * @var array<string, Order>
     */
    private array $orders = [];

    /**
     * @param list<Order> $orders
     */
    public function __construct(array $orders = [])
    {
        foreach ($orders as $order) {
            $this->add($order);
        }
    }

    public function add(Order $order): void
    {
        $this->orders[$order->id()] = $order;
    }

    public function list(OrderListCriteria $criteria): OrderListResult
    {
        $orders = array_values(array_filter(
            $this->orders,
            static function (Order $order) use ($criteria): bool {
                if ($criteria->customerId !== null && $order->customerId() !== $criteria->customerId) {
                    return false;
                }

                if ($criteria->storeId !== null && $order->storeId() !== $criteria->storeId) {
                    return false;
                }

                return $criteria->status === null || $order->status() === $criteria->status;
            },
        ));

        usort($orders, static function (Order $left, Order $right): int {
            if ($left->createdAt()->equalTo($right->createdAt())) {
                return strcmp($right->id(), $left->id());
            }

            return $left->createdAt()->lessThan($right->createdAt()) ? 1 : -1;
        });

        if ($criteria->cursor !== null) {
            $cursor = $criteria->cursor;

            $orders = array_values(array_filter(
                $orders,
                static function (Order $order) use ($cursor): bool {
                    if ($order->createdAt()->lessThan($cursor->createdAt)) {
                        return true;
                    }

                    return $order->createdAt()->equalTo($cursor->createdAt)
                        && strcmp($order->id(), $cursor->id) < 0;
                },
            ));
        }

        return new OrderListResult(array_slice($orders, 0, $criteria->limit));
    }
}
