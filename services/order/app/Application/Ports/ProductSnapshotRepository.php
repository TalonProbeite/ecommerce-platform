<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Domain\Catalog\ProductSnapshot;

interface ProductSnapshotRepository
{
    /**
     * @param list<string> $ids
     * @return array<string, ProductSnapshot> Keyed by product id.
     */
    public function findActiveByIds(array $ids): array;
}
