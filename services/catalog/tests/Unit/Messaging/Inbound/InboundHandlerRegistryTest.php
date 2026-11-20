<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Messaging\Inbound\Exceptions\UnknownRoutingKeyException;
use App\Messaging\Inbound\InboundHandler;
use App\Messaging\Inbound\InboundHandlerRegistry;
use App\Services\Inbound\Results\ProcessingResult;
use Carbon\CarbonImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

final class InboundHandlerRegistryTest extends TestCase
{
    #[Test]
    public function it_indexes_handlers_by_their_routing_key(): void
    {
        $handler = new RegistryTestHandler('order.created');

        $registry = new InboundHandlerRegistry([$handler]);

        self::assertTrue($registry->has('order.created'));
        self::assertSame($handler, $registry->get('order.created'));
    }

    #[Test]
    public function a_string_iterable_key_overrides_the_handler_routing_key(): void
    {
        $handler = new RegistryTestHandler('order.created');

        $registry = new InboundHandlerRegistry(['custom.key' => $handler]);

        self::assertTrue($registry->has('custom.key'));
        self::assertFalse($registry->has('order.created'));
        self::assertSame($handler, $registry->get('custom.key'));
    }

    #[Test]
    public function a_duplicate_routing_key_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        new InboundHandlerRegistry([
            new RegistryTestHandler('order.created'),
            new RegistryTestHandler('order.created'),
        ]);
    }

    #[Test]
    public function an_unknown_key_throws_an_unknown_routing_key_exception(): void
    {
        $registry = new InboundHandlerRegistry([new RegistryTestHandler('order.created')]);

        $this->expectException(UnknownRoutingKeyException::class);

        $registry->get('order.completed');
    }
}

final class RegistryTestHandler implements InboundHandler
{
    public function __construct(private readonly string $routingKey)
    {
    }

    public function routingKey(): string
    {
        return $this->routingKey;
    }

    public function dtoClass(): string
    {
        return stdClass::class;
    }

    public function handle(object $dto, CarbonImmutable $now): ProcessingResult
    {
        return ProcessingResult::applied();
    }
}
