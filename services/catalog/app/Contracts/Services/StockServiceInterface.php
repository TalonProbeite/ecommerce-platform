<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Stock;

interface StockServiceInterface
{
    /**
     * @throws \App\Exceptions\StockNotFoundException
     */
    public function get(string $storeId, string $productId): Stock;

    /**
     * Set total quantity. Rejects values below the current reserved count.
     *
     * @throws \App\Exceptions\StockNotFoundException
     * @throws \App\Exceptions\QuantityBelowReservedException
     */
    public function setQuantity(string $storeId, string $productId, int $quantity): Stock;

    /**
     * Increase reserved by $quantity. Rejects if available units are insufficient.
     *
     * @throws \App\Exceptions\StockNotFoundException
     * @throws \App\Exceptions\InsufficientStockException
     */
    public function reserveUnits(string $storeId, string $productId, int $quantity): Stock;

    /**
     * Decrease reserved by $quantity. Rejects if it would go below zero.
     *
     * @throws \App\Exceptions\StockNotFoundException
     * @throws \App\Exceptions\ReservedUnderflowException
     */
    public function releaseUnits(string $storeId, string $productId, int $quantity): Stock;
}