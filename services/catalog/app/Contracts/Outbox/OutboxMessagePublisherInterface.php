<?php

declare(strict_types=1);

namespace App\Contracts\Outbox;

interface OutboxMessagePublisherInterface
{
    /**
     * @param array<string, mixed> $body
     */
    public function publish(string $routingKey, array $body, string $messageId): void;
}
