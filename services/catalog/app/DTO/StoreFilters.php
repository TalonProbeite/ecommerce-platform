<?php

declare(strict_types=1);

namespace App\DTO;


final readonly class StoreFilters
{
    public ?bool $active;

    public function __construct(
        ?bool $active = true,
        public int $limit = 20,
        public int $page = 1,
        ?bool $isActive = null,
    ) {
        $this->active = $isActive ?? $active;
    }
}
