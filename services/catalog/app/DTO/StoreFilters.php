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

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            active: self::resolveActive($data),
            limit: (int) ($data['per_page'] ?? 20),
            page: (int) ($data['page'] ?? 1),
        );
    }

    /**
     * Absent: default filter (true). Explicit null: no filter (null).
     *
     * @param array<string, mixed> $data
     */
    private static function resolveActive(array $data): ?bool
    {
        if (! array_key_exists('active', $data)) {
            return true;
        }

        if ($data['active'] === null) {
            return null;
        }

        return (bool) filter_var($data['active'], FILTER_VALIDATE_BOOLEAN);
    }
}
