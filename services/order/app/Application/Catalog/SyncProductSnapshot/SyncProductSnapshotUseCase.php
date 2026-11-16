<?php

declare(strict_types=1);

namespace App\Application\Catalog\SyncProductSnapshot;

use App\Application\Catalog\SyncProductSnapshot\Commands\ProductDeleteCommand;
use App\Application\Catalog\SyncProductSnapshot\Commands\ProductUpsertCommand;
use App\Application\Ports\ProcessedEventStore;
use App\Application\Ports\ProductSnapshotRepository;
use App\Application\Ports\TransactionRunnerInterface;
use App\Domain\Catalog\ProductSnapshot;
use App\Domain\Messaging\RoutingKeys;
use App\Domain\Money\Money;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

final readonly class SyncProductSnapshotUseCase
{
    public function __construct(
        private TransactionRunnerInterface $transactions,
        private ProductSnapshotRepository $snapshots,
        private ProcessedEventStore $processedEvents,
        private LoggerInterface $logger,
    ) {}

    public function upsert(
        ProductUpsertCommand $command,
        CarbonImmutable $now,
        string $routingKey = RoutingKeys::PRODUCT_CREATED,
    ): void {
        $this->transactions->run(function () use ($command, $now, $routingKey): void {
            if (! $this->processedEvents->markProcessed($command->eventId, $routingKey, $now)) {
                return;
            }

            $existing = $this->snapshots->findById($command->productId);

            if ($existing !== null && $this->isStale($existing->sourceOccurredAt, $command->occurredAt)) {
                $this->logStale('product upsert', $command->productId, $command->eventId, $command->occurredAt, $existing->sourceOccurredAt);

                return;
            }

            // A newer upsert after a deletion resurrects the snapshot: the version check
            // already rejects older messages, so no separate "deleted" guard is needed.
            $this->snapshots->save($this->fromUpsert($command));
        });
    }

    public function delete(ProductDeleteCommand $command, CarbonImmutable $now): void
    {
        $this->transactions->run(function () use ($command, $now): void {
            if (! $this->processedEvents->markProcessed($command->eventId, RoutingKeys::PRODUCT_DELETED, $now)) {
                return;
            }

            $existing = $this->snapshots->findById($command->productId);

            if ($existing === null) {
                // No tombstone: ordering is guaranteed by a single relay and a single queue.
                $this->logger->info('Ignoring product deletion: snapshot does not exist.', [
                    'product_id' => $command->productId,
                    'event_id' => $command->eventId,
                ]);

                return;
            }

            if ($this->isStale($existing->sourceOccurredAt, $command->occurredAt)) {
                $this->logStale('product deletion', $command->productId, $command->eventId, $command->occurredAt, $existing->sourceOccurredAt);

                return;
            }

            if ($existing->deletedAt !== null) {
                return;
            }

            $this->snapshots->save($this->markDeleted($existing, $command->occurredAt, $now));
        });
    }

    private function isStale(CarbonImmutable $sourceOccurredAt, CarbonImmutable $incomingOccurredAt): bool
    {
        return $incomingOccurredAt->lessThanOrEqualTo($sourceOccurredAt);
    }

    private function fromUpsert(ProductUpsertCommand $command): ProductSnapshot
    {
        return new ProductSnapshot(
            productId: $command->productId,
            storeId: $command->storeId,
            categoryId: $command->categoryId,
            name: $command->name,
            pricePerHour: Money::fromMinorUnits($command->pricePerHour),
            depositPerUnit: Money::fromMinorUnits($command->depositAmount),
            isActive: $command->isActive,
            sourceOccurredAt: $command->occurredAt,
            deletedAt: null,
        );
    }

    private function markDeleted(ProductSnapshot $existing, CarbonImmutable $occurredAt, CarbonImmutable $deletedAt): ProductSnapshot
    {
        return new ProductSnapshot(
            productId: $existing->productId,
            storeId: $existing->storeId,
            categoryId: $existing->categoryId,
            name: $existing->name,
            pricePerHour: $existing->pricePerHour,
            depositPerUnit: $existing->depositPerUnit,
            isActive: $existing->isActive,
            sourceOccurredAt: $occurredAt,
            deletedAt: $deletedAt,
        );
    }

    private function logStale(
        string $operation,
        string $productId,
        string $eventId,
        CarbonImmutable $occurredAt,
        CarbonImmutable $sourceOccurredAt,
    ): void {
        $this->logger->info("Ignoring stale {$operation}.", [
            'product_id' => $productId,
            'event_id' => $eventId,
            'occurred_at' => $occurredAt->format('Y-m-d\TH:i:s.uP'),
            'source_occurred_at' => $sourceOccurredAt->format('Y-m-d\TH:i:s.uP'),
        ]);
    }
}
