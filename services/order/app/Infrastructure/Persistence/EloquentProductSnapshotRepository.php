<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\Clock;
use App\Application\Ports\ProductSnapshotRepository;
use App\Domain\Catalog\ProductSnapshot;
use App\Domain\Money\Money;
use App\Infrastructure\Persistence\Models\ProductSnapshotModel;
use Carbon\CarbonImmutable;

final readonly class EloquentProductSnapshotRepository implements ProductSnapshotRepository
{
    public function __construct(
        private Clock $clock,
    ) {
    }

    public function findById(string $productId): ?ProductSnapshot
    {
        $model = ProductSnapshotModel::query()->lockForUpdate()->find($productId);

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * @param list<string> $ids
     * @return array<string, ProductSnapshot>
     */
    public function findActiveByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $models = ProductSnapshotModel::query()
            ->whereIn('product_id', $ids)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get();

        $snapshots = [];

        foreach ($models as $model) {
            $snapshot = $this->toDomain($model);

            $snapshots[$snapshot->productId] = $snapshot;
        }

        return $snapshots;
    }

    public function save(ProductSnapshot $snapshot): void
    {
        $now = $this->clock->now();

        $model = ProductSnapshotModel::query()->find($snapshot->productId);

        if ($model === null) {
            $model = new ProductSnapshotModel();
            $model->product_id = $snapshot->productId;
            $model->created_at = $now->format('Y-m-d H:i:s.uP');
        }

        $model->store_id = $snapshot->storeId;
        $model->category_id = $snapshot->categoryId;
        $model->name = $snapshot->name;
        $model->price_per_hour = $snapshot->pricePerHour->amount();
        $model->deposit_amount = $snapshot->depositPerUnit->amount();
        $model->is_active = $snapshot->isActive;
        $model->source_occurred_at = $snapshot->sourceOccurredAt->format('Y-m-d H:i:s.uP');
        $model->deleted_at = $snapshot->deletedAt?->format('Y-m-d H:i:s.uP');
        $model->updated_at = $now->format('Y-m-d H:i:s.uP');

        $model->save();
    }

    private function toDomain(ProductSnapshotModel $model): ProductSnapshot
    {
        return new ProductSnapshot(
            productId: (string) $model->product_id,
            storeId: (string) $model->store_id,
            categoryId: (string) $model->category_id,
            name: (string) $model->name,
            pricePerHour: Money::fromMinorUnits((int) $model->price_per_hour),
            depositPerUnit: Money::fromMinorUnits((int) $model->deposit_amount),
            isActive: (bool) $model->is_active,
            sourceOccurredAt: $this->immutable($model->source_occurred_at),
            deletedAt: $model->deleted_at !== null ? $this->immutable($model->deleted_at) : null,
        );
    }

    private function immutable(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse((string) $value);
    }
}
