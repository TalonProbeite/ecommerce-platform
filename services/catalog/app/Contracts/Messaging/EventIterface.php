<?php

declare(strict_types=1);

namespace App\Contracts\Messaging;

interface EventInterface
{
    public function routingKey(): string;

    public function toArray(): array;
}
