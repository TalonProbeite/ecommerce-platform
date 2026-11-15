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
    ) {
    }

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

            if ($existing === null) {
                $this->snapshots->save(new ProductSnapshot(
                    productId: $data->productId,
                    storeId: $data->storeId,
                    categoryId: $data->categoryId,
                    name: $data->name,
                    pricePerHour: Money::fromMinorUnits($data->pricePerHour),
                    depositPerUnit: Money::fromMinorUnits($data->depositAmount),
                    isActive: $data->isActive,
                    sourceOccurredAt: $data->occurredAt,
                ));

                return;
            }

            if ($existing->deletedAt !== null) {
                $this->logger->info('Ignoring product update for a deleted product snapshot.', [
                    'product_id' => $data->productId,
                    'event_id' => $data->eventId,
                ]);

                return;
            }

            if ($data->occurredAt->lessThanOrEqualTo($existing->sourceOccurredAt)) {
                $this->logger->info('Ignoring stale product update.', [
                    'product_id' => $data->productId,
                    'event_id' => $data->eventId,
                    'occurred_at' => $data->occurredAt->format('Y-m-d\TH:i:s.uP'),
                    'source_occurred_at' => $existing->sourceOccurredAt->format('Y-m-d\TH:i:s.uP'),
                ]);

                return;
            }

            $this->snapshots->save(new ProductSnapshot(
                productId: $data->productId,
                storeId: $data->storeId,
                categoryId: $data->categoryId,
                name: $data->name,
                pricePerHour: Money::fromMinorUnits($data->pricePerHour),
                depositPerUnit: Money::fromMinorUnits($data->depositAmount),
                isActive: $data->isActive,
                sourceOccurredAt: $data->occurredAt,
                deletedAt: null,
            ));
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
                $this->logger->info('Ignoring product deletion because the snapshot does not exist.', [
                    'product_id' => $data->productId,
                    'event_id' => $data->eventId,
                ]);

                return;
            }

            if ($existing->deletedAt !== null) {
                return;
            }

            if ($data->occurredAt->lessThanOrEqualTo($existing->sourceOccurredAt)) {
                $this->logger->info('Ignoring stale product deletion.', [
                    'product_id' => $data->productId,
                    'event_id' => $data->eventId,
                    'occurred_at' => $data->occurredAt->format('Y-m-d\TH:i:s.uP'),
                    'source_occurred_at' => $existing->sourceOccurredAt->format('Y-m-d\TH:i:s.uP'),
                ]);

                return;
            }

            $this->snapshots->save(new ProductSnapshot(
                productId: $existing->productId,
                storeId: $existing->storeId,
                categoryId: $existing->categoryId,
                name: $existing->name,
                pricePerHour: $existing->pricePerHour,
                depositPerUnit: $existing->depositPerUnit,
                isActive: $existing->isActive,
                sourceOccurredAt: $data->occurredAt,
                deletedAt: $now,
            ));
        });
    }
}
