<?php

declare(strict_types=1);

namespace App\Application\Catalog\SyncProductSnapshot;

use App\Application\Ports\ProcessedEventStore;
use App\Application\Ports\ProductSnapshotRepository;
use App\Application\Ports\TransactionRunnerInterface;
use App\Domain\Catalog\ProductSnapshot;
use App\Domain\Money\Money;
use App\Infrastructure\Messaging\Inbound\Data\ProductDeletedData;
use App\Infrastructure\Messaging\Inbound\Data\ProductUpsertData;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
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
        ProductUpsertData $data,
        CarbonImmutable $now,
        string $routingKey = InboundRoutingKeys::PRODUCT_CREATED,
    ): void {
        $this->transactions->run(function () use ($data, $now, $routingKey): void {
            if (! $this->processedEvents->markProcessed($data->eventId, $routingKey, $now)) {
                return;
            }

            $existing = $this->snapshots->findById($data->productId);

            if ($existing !== null && $this->isStale($existing->sourceOccurredAt, $data->occurredAt)) {
                $this->logStale('product upsert', $data->productId, $data->eventId, $data->occurredAt, $existing->sourceOccurredAt);

                return;
            }

            // A newer upsert after a deletion resurrects the snapshot: the version check
            // already rejects older messages, so no separate "deleted" guard is needed.
            $this->snapshots->save($this->fromUpsert($data));
        });
    }

    public function delete(ProductDeletedData $data, CarbonImmutable $now): void
    {
        $this->transactions->run(function () use ($data, $now): void {
            if (! $this->processedEvents->markProcessed($data->eventId, InboundRoutingKeys::PRODUCT_DELETED, $now)) {
                return;
            }

            $existing = $this->snapshots->findById($data->productId);

            if ($existing === null) {
                // No tombstone: ordering is guaranteed by a single relay and a single queue.
                $this->logger->info('Ignoring product deletion: snapshot does not exist.', [
                    'product_id' => $data->productId,
                    'event_id' => $data->eventId,
                ]);

                return;
            }

            if ($this->isStale($existing->sourceOccurredAt, $data->occurredAt)) {
                $this->logStale('product deletion', $data->productId, $data->eventId, $data->occurredAt, $existing->sourceOccurredAt);

                return;
            }

            if ($existing->deletedAt !== null) {
                return;
            }

            $this->snapshots->save($this->markDeleted($existing, $data->occurredAt, $now));
        });
    }

    private function isStale(CarbonImmutable $sourceOccurredAt, CarbonImmutable $incomingOccurredAt): bool
    {
        return $incomingOccurredAt->lessThanOrEqualTo($sourceOccurredAt);
    }

    private function fromUpsert(ProductUpsertData $data): ProductSnapshot
    {
        return new ProductSnapshot(
            productId: $data->productId,
            storeId: $data->storeId,
            categoryId: $data->categoryId,
            name: $data->name,
            pricePerHour: Money::fromMinorUnits($data->pricePerHour),
            depositPerUnit: Money::fromMinorUnits($data->depositAmount),
            isActive: $data->isActive,
            sourceOccurredAt: $data->occurredAt,
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
