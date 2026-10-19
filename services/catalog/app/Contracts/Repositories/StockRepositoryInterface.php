<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Collection;

interface StockRepositoryInterface
{
    public function findByProductAndStore(string $productID, string $storeID): ?Stock;
    /**
     * Undocumented function
     *
     * @param string $storeID
     * @return Collection<int,Stock>
     */
    public function findByStore(string $storeID): Collection;
    public function save(Stock $category): Stock;
    public function delete(Stock $category): void;
}
