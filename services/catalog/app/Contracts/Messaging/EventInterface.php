<?php

declare(strict_types=1);

namespace App\Contracts\Messaging;

interface EventInterface
{
    /**
     * Get the routing key for the event.
     */
    public function routingKey(): string;

    /**
     * Convert the event to an array payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
