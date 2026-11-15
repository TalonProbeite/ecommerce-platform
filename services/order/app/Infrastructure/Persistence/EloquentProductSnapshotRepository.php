<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\ProductSnapshotRepository;
use App\Domain\Catalog\ProductSnapshot;
use App\Domain\Money\Money;
use App\Infrastructure\Persistence\Models\ProductSnapshotModel;
use Carbon\CarbonImmutable;

final class EloquentProductSnapshotRepository implements ProductSnapshotRepository
{
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
            $snapshot = new ProductSnapshot(
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

            $snapshots[$snapshot->productId] = $snapshot;
        }

        return $snapshots;
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
