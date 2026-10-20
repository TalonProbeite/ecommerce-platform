<?php

declare(strict_types=1);

namespace App\Contracts\Messaging;

interface EventInterface
{
    public function routingKey(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
