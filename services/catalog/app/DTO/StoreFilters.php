<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class StoreFilters
{
    /**
     * Create a new store filters instance.
     */
    public function __construct(
        public ?bool $active = true,
        public int $limit = 20,
        public int $page = 1,
    ) {
    }
}
