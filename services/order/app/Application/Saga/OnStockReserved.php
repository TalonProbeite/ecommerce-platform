<?php

declare(strict_types=1);

namespace App\Application\Saga;

use App\Application\Exceptions\OrderNotFound;
use App\Application\Exceptions\StoreMismatch;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\ProcessedEventStore;
use App\Application\Ports\TransactionRunnerInterface;
use App\Application\Saga\Commands\StockReservedCommand;
use App\Domain\Order\OrderStatus;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

final readonly class OnStockReserved
{
    private const string ROUTING_KEY = 'stock.reserved';

    public function __construct(
        private TransactionRunnerInterface $transactions,
        private ProcessedEventStore $processedEvents,
        private OrderRepository $orders,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(StockReservedCommand $command, CarbonImmutable $now): void
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
                $this->logger->info('Ignoring stock reservation for an order in a non-created status.', [
                    'order_id' => $order->id(),
                    'event_id' => $command->eventId,
                    'status' => $order->status()->value,
                ]);

                return;
            }

            $order->markStockReserved($now);
            $this->orders->save($order);
        });
    }
}
