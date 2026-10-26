<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Non-null values imply presence. To explicitly supply null, set the matching
 * Provided argument to true. A false Provided property means leave unchanged.
 * Only nullable schema fields (description, location) may be cleared; the service must
 * reject explicit null for all other fields as an invalid business operation.
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
