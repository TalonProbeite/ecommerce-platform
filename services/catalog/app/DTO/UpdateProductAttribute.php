<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class UpdateProductAttribute
{
    public bool $nameProvided;
    public bool $valueProvided;

    public function __construct(
        public ?string $name = null,
        public ?string $value = null,
        bool $nameProvided = false,
        bool $valueProvided = false,
    ) {
        $this->valueProvided = $valueProvided || $value !== null;
        $this->nameProvided = $nameProvided || $name !== null;
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->nameProvided) {
            $data['name'] = $this->name;
        }

        if ($this->valueProvided) {
            $data['value'] = $this->value;
        }

        return $data;
    }
}
