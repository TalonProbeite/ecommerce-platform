<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Contracts\Outbox\OutboxWriterInterface;
use App\Contracts\Repositories\ProcessedEventRepositoryInterface;
use App\Contracts\Repositories\StockRepositoryInterface;
use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Contracts\Services\Inbound\OrderStockReservationServiceInterface;
use App\Contracts\Database\TransactionRunnerInterface;
use App\Enums\ReservationStatus;
use App\Messaging\Events\StockChanged;
use App\Messaging\Events\StockReservationFailed;
use App\Messaging\Events\StockReservationFailureReason;
use App\Messaging\Events\StockReserved;
use App\Messaging\Inbound\Data\OrderCancelledData;
use App\Messaging\Inbound\Data\OrderCreatedData;
use App\Messaging\Inbound\Data\OrderCreatedItemData;
use App\Messaging\Inbound\Data\OrderLineData;
use App\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Messaging\Inbound\Exceptions\InvalidReservationTransitionException;
use App\Messaging\Inbound\Exceptions\RetryableMessageException;
use App\Messaging\Inbound\InboundRoutingKeys;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Services\Inbound\Results\ProcessingResult;
use App\Services\Inbound\Results\StockRejectionReason;
use Carbon\CarbonImmutable;

final class OrderStockReservationService implements OrderStockReservationServiceInterface
{
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:sP';

    public function __construct(
        private readonly StockRepositoryInterface $stocks,
        private readonly StockReservationRepositoryInterface $reservations,
        private readonly ProcessedEventRepositoryInterface $processedEvents,
        private readonly TransactionRunnerInterface $transactions,
        private readonly OutboxWriterInterface $outbox,
    ) {
    }

    public function handleCreated(OrderCreatedData $data, CarbonImmutable $now): ProcessingResult
    {
        return $this->transactions->run(function () use ($data, $now): ProcessingResult {
            if (! $this->processedEvents->markProcessed($data->eventId, InboundRoutingKeys::ORDER_CREATED, $now)) {
                return ProcessingResult::duplicate();
            }

            $existing = array_flip($this->reservations->findExistingLineIds(
                array_map(static fn (OrderCreatedItemData $item): string => $item->lineId, $data->items),
            ));

            $existingCount = count($existing);
            $totalCount = count($data->items);

            if ($existingCount > 0) {
                if ($existingCount < $totalCount) {
                    logger()->warning('Partial existing lines detected for order', [
                        'order_id' => $data->orderId,
                        'event_id' => $data->eventId,
                    ]);
                }

                return ProcessingResult::applied();
            }

            $pending = $data->items;

            usort(
                $pending,
                static fn (OrderCreatedItemData $left, OrderCreatedItemData $right): int => strcmp($left->lineId, $right->lineId),
            );

            /** @var array<string, int> $requiredByProduct */
            $requiredByProduct = [];

            foreach ($pending as $item) {
                $requiredByProduct[$item->productId] = ($requiredByProduct[$item->productId] ?? 0) + $item->quantity;
            }

            $stocks = $this->lockStocks(array_keys($requiredByProduct), $data->storeId);

            foreach ($requiredByProduct as $productId => $required) {
                if (! isset($stocks[$productId])) {
                    $this->outbox->enqueue(
                        new StockReservationFailed($data->orderId, $data->storeId, StockReservationFailureReason::ProductNotFound),
                        $now,
                    );

                    return ProcessingResult::rejected(StockRejectionReason::ProductNotFound);
                }

                $stock = $stocks[$productId];

                if ($stock->quantity - $stock->reserved < $required) {
                    $this->outbox->enqueue(
                        new StockReservationFailed($data->orderId, $data->storeId, StockReservationFailureReason::InsufficientStock),
                        $now,
                    );

                    return ProcessingResult::rejected(StockRejectionReason::InsufficientStock);
                }
            }

            $rows = array_map(
                static fn (OrderCreatedItemData $item): array => [
                    'order_line_id' => $item->lineId,
                    'order_id' => $data->orderId,
                    'product_id' => $item->productId,
                    'store_id' => $data->storeId,
                    'quantity' => $item->quantity,
                    'status' => ReservationStatus::Reserved->value,
                    'rental_start_at' => $item->rentalStartAt->format(self::TIMESTAMP_FORMAT),
                    'rental_end_at' => $item->rentalEndAt->format(self::TIMESTAMP_FORMAT),
                ],
                $pending,
            );

            foreach ($requiredByProduct as $productId => $required) {
                $this->adjustReserved($stocks[$productId], $required);
            }

            if ($this->reservations->insertIgnoringExisting($rows) !== count($pending)) {
                throw new RetryableMessageException('Reservation insert raced with another order event; retry existing-line check.');
            }

            $this->outbox->enqueue(new StockReserved($data->orderId, $data->storeId), $now);

            foreach (array_keys($requiredByProduct) as $productId) {
                $updatedStock = $this->stocks->findByProductId($productId, $data->storeId);
                if ($updatedStock !== null) {
                    $this->outbox->enqueue(StockChanged::fromModel($updatedStock), $now);
                }
            }

            return ProcessingResult::applied(array_keys($requiredByProduct));
        });
    }

    public function handleCancelled(OrderCancelledData $data, CarbonImmutable $now): ProcessingResult
    {
        return $this->transactions->run(function () use ($data, $now): ProcessingResult {
            if (! $this->processedEvents->markProcessed($data->eventId, InboundRoutingKeys::ORDER_CANCELLED, $now)) {
                return ProcessingResult::duplicate();
            }

            $productIds = array_map(static fn (OrderLineData $item): string => $item->productId, $data->items);
            $stocks = $this->lockStocks($productIds, $data->storeId);

            $items = $data->items;
            usort($items, static fn (OrderLineData $left, OrderLineData $right): int => strcmp($left->lineId, $right->lineId));

            $affected = [];

            foreach ($items as $item) {
                $reservation = $this->reservations->findByLineIdForUpdate($item->lineId);

                if ($reservation === null) {
                    // Tombstone: a late order.created must not reserve this line again.
                    $this->reservations->insertIgnoringExisting([[
                        'order_line_id' => $item->lineId,
                        'order_id' => $data->orderId,
                        'product_id' => $item->productId,
                        'store_id' => $data->storeId,
                        'quantity' => $item->quantity,
                        'status' => ReservationStatus::Released->value,
                        'rental_start_at' => null,
                        'rental_end_at' => null,
                    ]]);

                    continue;
                }

                if ((string) $reservation->order_id !== $data->orderId
                    || (string) $reservation->store_id !== $data->storeId
                    || (string) $reservation->product_id !== $item->productId) {
                    throw new InvalidMessageException([], 'Reservation identity does not match the order event.');
                }

                $current = ReservationStatus::from((string) $reservation->status);

                if ($current === ReservationStatus::Released) {
                    continue;
                }

                if ($current !== ReservationStatus::Reserved) {
                    throw new InvalidReservationTransitionException(
                        $item->lineId,
                        $current,
                        ReservationStatus::Reserved,
                        ReservationStatus::Released,
                    );
                }

                $this->transition($reservation, ReservationStatus::Reserved, ReservationStatus::Released, $now);

                $stock = $stocks[$item->productId] ?? null;

                if ($stock === null) {
                    throw new RetryableMessageException(
                        sprintf('Stock row disappeared while cancelling product "%s".', $item->productId),
                    );
                }

                $this->adjustReserved($stock, -$reservation->quantity);
                $affected[$item->productId] = true;
            }

            foreach (array_keys($affected) as $productId) {
                $updatedStock = $this->stocks->findByProductId($productId, $data->storeId);
                if ($updatedStock !== null) {
                    $this->outbox->enqueue(StockChanged::fromModel($updatedStock), $now);
                }
            }

            return ProcessingResult::applied(array_keys($affected));
        });
    }

    /**
     * Lock stock rows in ascending product_id order to avoid deadlocks.
     * Missing rows are omitted; callers decide whether that is a rejection or a retry.
     *
     * @param list<string> $productIds
     * @return array<string, Stock>
     */
    private function lockStocks(array $productIds, string $storeId): array
    {
        $productIds = array_values(array_unique($productIds));
        sort($productIds, SORT_STRING);

        $stocks = [];

        foreach ($productIds as $productId) {
            $stock = $this->stocks->lockByProductId($productId, $storeId);

            if ($stock !== null) {
                $stocks[$productId] = $stock;
            }
        }

        return $stocks;
    }

    private function transition(
        StockReservation $reservation,
        ReservationStatus $from,
        ReservationStatus $to,
        CarbonImmutable $now,
    ): void {
        $affected = $this->reservations->transitionStatus(
            (string) $reservation->order_line_id,
            $from,
            $to,
            $now,
        );

        if ($affected !== 1) {
            throw new RetryableMessageException(sprintf(
                'Failed to transition order line "%s" from "%s" to "%s".',
                (string) $reservation->order_line_id,
                $from->value,
                $to->value,
            ));
        }
    }

    private function adjustReserved(Stock $stock, int $delta): void
    {
        if ($this->stocks->adjustReserved($stock->id, $delta) !== 1) {
            throw new RetryableMessageException(sprintf(
                'Failed to adjust reserved stock for product "%s" by %d.',
                $stock->product_id,
                $delta,
            ));
        }
    }
}
