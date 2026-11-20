<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\OrderEventReadRepository;
use App\Application\Query\ListOrderEvents\ListOrderEventsCriteria;
use App\Application\Query\ListOrderEvents\OrderEventCursor;
use App\Application\Query\ListOrderEvents\OrderEventItem;
use App\Application\Query\ListOrderEvents\OrderEventPage;

final class InMemoryOrderEventReadRepository implements OrderEventReadRepository
{
    /**
     * @var array<string, string>
     */
    private array $orders = [];

    /**
     * @var list<OrderEventItem>
     */
    private array $events = [];

    public ?ListOrderEventsCriteria $criteria = null;

    public function addOrder(string $orderId, string $storeId): void
    {
        $this->orders[$orderId] = $storeId;
    }

    public function addEvent(OrderEventItem $event, string $orderId, string $storeId): void
    {
        $this->events[] = $event;
        $this->orders[$orderId] = $storeId;
    }

    public function storeIdOf(string $orderId): ?string
    {
        return $this->orders[$orderId] ?? null;
    }

    public function list(ListOrderEventsCriteria $criteria): OrderEventPage
    {
        $this->criteria = $criteria;

        $events = array_values(array_filter(
            $this->events,
            static function (OrderEventItem $event) use ($criteria): bool {
                if ($criteria->cursor === null) {
                    return true;
                }

                if ($event->occurredAt->greaterThan($criteria->cursor->occurredAt)) {
                    return true;
                }

                return $event->occurredAt->equalTo($criteria->cursor->occurredAt)
                    && (int) $event->id > (int) $criteria->cursor->id;
            },
        ));

        usort($events, static function (OrderEventItem $left, OrderEventItem $right): int {
            if ($left->occurredAt->equalTo($right->occurredAt)) {
                return (int) $left->id <=> (int) $right->id;
            }

            return $left->occurredAt->lessThan($right->occurredAt) ? -1 : 1;
        });

        $hasMore = count($events) > $criteria->limit;
        $events = array_slice($events, 0, $criteria->limit);

        $nextCursor = null;

        if ($hasMore && $events !== []) {
            $last = $events[array_key_last($events)];
            $nextCursor = (new OrderEventCursor($last->occurredAt, $last->id))->encode();
        }

        return new OrderEventPage($events, $nextCursor);
    }
}
