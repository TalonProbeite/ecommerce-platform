<?php

declare(strict_types=1);

namespace App\Application\Saga;

use App\Application\Ports\OrderRepository;
use App\Application\Ports\ProcessedEventStore;
use App\Application\Ports\TransactionRunnerInterface;
use App\Domain\Order\OrderStatus;
use App\Infrastructure\Messaging\Inbound\Data\StockReservationFailedData;
use App\Infrastructure\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

final readonly class OnStockReservationFailed
{
    public function __construct(
        private TransactionRunnerInterface $transactions,
        private ProcessedEventStore $processedEvents,
        private OrderRepository $orders,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(StockReservationFailedData $data, CarbonImmutable $now): void
    {
        $this->transactions->run(function () use ($data, $now): void {
            if (! $this->processedEvents->markProcessed(
                $data->eventId,
                InboundRoutingKeys::STOCK_RESERVATION_FAILED,
                $now,
            )) {
                return;
            }

            $order = $this->orders->findByIdForUpdate($data->orderId);

            if ($order === null) {
                $this->logger->error('Stock reservation failure received for a missing order.', [
                    'order_id' => $data->orderId,
                    'event_id' => $data->eventId,
                ]);

                throw new InvalidMessageException(
                    ['order_id' => ['The order does not exist.']],
                    'Stock reservation failure received for a missing order.',
                );
            }

            if ($order->status() !== OrderStatus::CREATED) {
                $this->logger->info('Ignoring stock reservation failure for an order in a non-created status.', [
                    'order_id' => $order->id(),
                    'event_id' => $data->eventId,
                    'status' => $order->status()->value,
                    'reason' => $data->reason,
                ]);

                return;
            }

            // Catalog did not reserve anything for a failed reservation, so no
            // compensating outbound event is written here.
            $order->markStockReservationFailed($now);
            $this->orders->save($order);
        });
    }
}
