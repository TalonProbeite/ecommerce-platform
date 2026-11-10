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

    /**
     * @return array{name: string, description: string|null, location: string|null, active: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'active' => $this->active,
        ];
    }
}
