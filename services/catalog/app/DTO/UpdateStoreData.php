<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Non-null values imply presence. To explicitly supply null, set the matching
 * Provided argument to true. A false Provided property means leave unchanged.
 * Only nullable schema fields (description, location) may be cleared; HTTP validation
 * (Form Requests) must reject explicit null for all other fields.
 */
final readonly class UpdateStoreData
{
    public bool $nameProvided;
    public bool $descriptionProvided;
    public bool $locationProvided;
    public bool $activeProvided;

    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $location = null,
        public ?bool $active = null,
        bool $nameProvided = false,
        bool $descriptionProvided = false,
        bool $locationProvided = false,
        bool $activeProvided = false,
    ) {
        $this->nameProvided = $nameProvided || $name !== null;
        $this->descriptionProvided = $descriptionProvided || $description !== null;
        $this->locationProvided = $locationProvided || $location !== null;
        $this->activeProvided = $activeProvided || $active !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            location: isset($data['location']) ? (string) $data['location'] : null,
            active: array_key_exists('active', $data) && $data['active'] !== null
                ? (bool) filter_var($data['active'], FILTER_VALIDATE_BOOLEAN)
                : null,
            nameProvided: array_key_exists('name', $data),
            descriptionProvided: array_key_exists('description', $data),
            locationProvided: array_key_exists('location', $data),
            activeProvided: array_key_exists('active', $data),
        );
    }

    /**
     * @return array{name?: string|null, description?: string|null, location?: string|null, active?: bool|null}
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->nameProvided) {
            $data['name'] = $this->name;
        }

        if ($this->descriptionProvided) {
            $data['description'] = $this->description;
        }

        if ($this->locationProvided) {
            $data['location'] = $this->location;
        }

        if ($this->activeProvided) {
            $data['active'] = $this->active;
        }

        return $data;
    }
}
