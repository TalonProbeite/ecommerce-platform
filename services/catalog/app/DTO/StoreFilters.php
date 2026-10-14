<?php

declare(strict_types=1);

namespace App\DTO;


final readonly class StoreFilters
{
    public function __construct(
        public ?float $minRating = null,
        public ?bool $isActive = true,

        public int $limit = 20,
        public int $page = 1,
    ) {}
}
