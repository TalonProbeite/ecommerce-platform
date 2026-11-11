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
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            location: isset($data['location']) ? (string) $data['location'] : null,
            active: array_key_exists('active', $data) && $data['active'] !== null
                ? (bool) filter_var($data['active'], FILTER_VALIDATE_BOOLEAN)
                : true,
        );
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
