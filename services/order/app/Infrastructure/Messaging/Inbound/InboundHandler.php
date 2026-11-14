<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound;

use Carbon\CarbonImmutable;

interface InboundHandler
{
    public function routingKey(): string;

    /**
     * @return class-string
     */
    public function dtoClass(): string;

    public function handle(object $dto, CarbonImmutable $now): mixed;
}
