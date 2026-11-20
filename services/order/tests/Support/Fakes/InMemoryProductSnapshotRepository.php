<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\ProductSnapshotRepository;
use App\Domain\Catalog\ProductSnapshot;

final class InMemoryProductSnapshotRepository implements ProductSnapshotRepository
{
    /**
     * @var array<string, ProductSnapshot>
     */
    private array $snapshots = [];

    /**
     * @param list<ProductSnapshot> $snapshots
     */
    public function __construct(array $snapshots = [])
    {
        foreach ($snapshots as $snapshot) {
            $this->snapshots[$snapshot->productId] = $snapshot;
        }
    }

    public function add(ProductSnapshot $snapshot): void
    {
        $this->snapshots[$snapshot->productId] = $snapshot;
    }

    public function findById(string $productId): ?ProductSnapshot
    {
        return $this->snapshots[$productId] ?? null;
    }

    /**
     * @param list<string> $ids
     * @return array<string, ProductSnapshot>
     */
    public function findActiveByIds(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            $snapshot = $this->snapshots[$id] ?? null;

            if ($snapshot !== null && $snapshot->isAvailable()) {
                $result[$id] = $snapshot;
            }
        }

        return $result;
    }

    public function save(ProductSnapshot $snapshot): void
    {
        $this->snapshots[$snapshot->productId] = $snapshot;
    }
}
