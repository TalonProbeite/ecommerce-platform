<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Catalog input only; store ownership is supplied separately where applicable.
 */
final readonly class CreateStoreData
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $location = null,
        public bool $active = true,
    ) {
    }
}
