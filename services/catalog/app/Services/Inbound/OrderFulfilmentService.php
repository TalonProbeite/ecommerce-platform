<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Contracts\Database\TransactionRunnerInterface;
use App\Contracts\Outbox\OutboxWriterInterface;
use App\Contracts\Repositories\ProcessedEventRepositoryInterface;
use App\Contracts\Repositories\StockRepositoryInterface;
use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Contracts\Services\Inbound\OrderFulfilmentServiceInterface;
use App\Enums\ReservationStatus;
use App\Messaging\Events\StockChanged;
use App\Messaging\Inbound\Data\OrderIssuedData;
use App\Messaging\Inbound\Data\OrderLineData;
use App\Messaging\Inbound\Data\OrderReturnedData;
use App\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Messaging\Inbound\Exceptions\InvalidReservationTransitionException;
use App\Messaging\Inbound\Exceptions\ReservationNotFoundException;
use App\Messaging\Inbound\Exceptions\RetryableMessageException;
use App\Messaging\Inbound\InboundRoutingKeys;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Services\Inbound\Results\ProcessingResult;
use Carbon\CarbonImmutable;

final class OrderFulfilmentService implements OrderFulfilmentServiceInterface
{
    public function __construct(
        private readonly StockRepositoryInterface $stocks,
        private readonly StockReservationRepositoryInterface $reservations,
        private readonly ProcessedEventRepositoryInterface $processedEvents,
        private readonly TransactionRunnerInterface $transactions,
        private readonly OutboxWriterInterface $outbox,
    ) {
    }

    public function handleIssued(OrderIssuedData $data, CarbonImmutable $now): ProcessingResult
    {
        return $this->transactions->run(function () use ($data, $now): ProcessingResult {
            if (! $this->processedEvents->markProcessed($data->eventId, InboundRoutingKeys::ORDER_ISSUED, $now)) {
                return ProcessingResult::duplicate();
            }

            foreach ($this->sortedItems($data->items) as $item) {
                $reservation = $this->reservations->findByLineIdForUpdate($item->lineId)
                    ?? throw new ReservationNotFoundException($item->lineId);

                if ((string) $reservation->order_id !== $data->orderId
                    || (string) $reservation->store_id !== $data->storeId
                    || (string) $reservation->product_id !== $item->productId) {
                    throw new InvalidMessageException([], 'Reservation identity does not match the order event.');
                }

                $current = ReservationStatus::from((string) $reservation->status);

                if ($current === ReservationStatus::Issued) {
                    continue;
                }

                if ($current !== ReservationStatus::Reserved) {
                    throw new InvalidReservationTransitionException(
                        $item->lineId,
                        $current,
                        ReservationStatus::Reserved,
                        ReservationStatus::Issued,
                    );
                }

                $this->transition($reservation, ReservationStatus::Reserved, ReservationStatus::Issued, $now);
            }

            // Issuing does not change stock counters.
            return ProcessingResult::applied();
        });
    }

    public function handleReturned(OrderReturnedData $data, CarbonImmutable $now): ProcessingResult
    {
        return $this->transactions->run(function () use ($data, $now): ProcessingResult {
            if (! $this->processedEvents->markProcessed($data->eventId, InboundRoutingKeys::ORDER_RETURNED, $now)) {
                return ProcessingResult::duplicate();
            }

            $stocks = $this->lockStocks($data->items, $data->storeId);

            $affected = [];

            foreach ($this->sortedItems($data->items) as $item) {
                $reservation = $this->reservations->findByLineIdForUpdate($item->lineId)
                    ?? throw new ReservationNotFoundException($item->lineId);

                if ((string) $reservation->order_id !== $data->orderId
                    || (string) $reservation->store_id !== $data->storeId
                    || (string) $reservation->product_id !== $item->productId) {
                    throw new InvalidMessageException([], 'Reservation identity does not match the order event.');
                }

                $current = ReservationStatus::from((string) $reservation->status);

                if ($current === ReservationStatus::Returned) {
                    continue;
                }

                if ($current === ReservationStatus::Reserved) {
                    throw new RetryableMessageException('Return arrived before the reservation was issued.');
                }

                if ($current !== ReservationStatus::Issued) {
                    throw new InvalidReservationTransitionException(
                        $item->lineId,
                        $current,
                        ReservationStatus::Issued,
                        ReservationStatus::Returned,
                    );
                }

                $this->transition($reservation, ReservationStatus::Issued, ReservationStatus::Returned, $now);

                $stock = $stocks[$item->productId] ?? null;

                if ($stock === null) {
                    throw new RetryableMessageException(
                        sprintf('Stock row disappeared while returning product "%s".', $item->productId),
                    );
                }

                // Issued units still count as reserved, so returning releases them.
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
     * @param list<OrderLineData> $items
     * @return list<OrderLineData>
     */
    private function sortedItems(array $items): array
    {
        usort(
            $items,
            static fn (OrderLineData $left, OrderLineData $right): int => strcmp($left->lineId, $right->lineId),
        );

        return $items;
    }

    /**
     * Lock stock rows in ascending product_id order to avoid deadlocks.
     * Missing rows are omitted; a stock row is only required when counters change.
     *
     * @param list<OrderLineData> $items
     * @return array<string, Stock>
     */
    private function lockStocks(array $items, string $storeId): array
    {
        $productIds = array_values(array_unique(array_map(
            static fn (OrderLineData $item): string => $item->productId,
            $items,
        )));
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
