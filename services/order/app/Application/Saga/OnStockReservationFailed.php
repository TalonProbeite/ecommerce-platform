<?php

declare(strict_types=1);

namespace App\Application\Saga;

use App\Application\Exceptions\OrderNotFound;
use App\Application\Exceptions\StoreMismatch;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\ProcessedEventStore;
use App\Application\Ports\TransactionRunnerInterface;
use App\Application\Saga\Commands\StockReservationFailedCommand;
use App\Domain\Order\OrderStatus;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

final readonly class OnStockReservationFailed
{
    private const string ROUTING_KEY = 'stock.reservation_failed';

    public function __construct(
        private TransactionRunnerInterface $transactions,
        private ProcessedEventStore $processedEvents,
        private OrderRepository $orders,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(StockReservationFailedCommand $command, CarbonImmutable $now): void
    {
        $this->transactions->run(function () use ($command, $now): void {
            $order = $this->orders->findByIdForUpdate($command->orderId);

            if ($order === null) {
                throw new OrderNotFound($command->orderId);
            }

            if ($order->storeId() !== $command->storeId) {
                throw new StoreMismatch($order->id(), $order->storeId(), $command->storeId);
            }

            if (! $this->processedEvents->markProcessed(
                $command->eventId,
                self::ROUTING_KEY,
                $now,
            )) {
                return;
            }

            if ($order->status() !== OrderStatus::CREATED) {
                $level = $order->status() === OrderStatus::PAID ? 'warning' : 'info';

                $this->logger->log($level, 'Ignoring stock reservation failure for an order in a non-created status.', [
                    'order_id' => $order->id(),
                    'event_id' => $command->eventId,
                    'status' => $order->status()->value,
                    'reason' => $command->reason->value,
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
