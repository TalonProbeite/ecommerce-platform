<?php

declare(strict_types=1);

namespace App\Messaging\Inbound;

use App\Services\Inbound\Results\ProcessingResult;
use Carbon\CarbonImmutable;

/**
 * @template T of object
 */
interface InboundHandler
{
    public function routingKey(): string;

    /**
     * @return class-string<T>
     */
    public function dtoClass(): string;

    /**
     * @param T $dto
     */
    public function handle(object $dto, CarbonImmutable $now): ProcessingResult;
}
