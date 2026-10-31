<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ProductAttributeData
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
    }
    /**
     * @return array<string, int|bool|string>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
        ];
    }
}
