<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Infrastructure\Messaging\Inbound\Exceptions\UnknownRoutingKeyException;
use App\Infrastructure\Messaging\Inbound\InboundHandler;
use App\Infrastructure\Messaging\Inbound\InboundHandlerRegistry;
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
        $handler = new RegistryTestHandler('stock.reserved');

        $registry = new InboundHandlerRegistry([$handler]);

        self::assertTrue($registry->has('stock.reserved'));
        self::assertSame($handler, $registry->get('stock.reserved'));
    }

    #[Test]
    public function a_string_iterable_key_overrides_the_handler_routing_key(): void
    {
        $handler = new RegistryTestHandler('stock.reserved');

        $registry = new InboundHandlerRegistry(['custom.key' => $handler]);

        self::assertTrue($registry->has('custom.key'));
        self::assertFalse($registry->has('stock.reserved'));
        self::assertSame($handler, $registry->get('custom.key'));
    }

    #[Test]
    public function a_duplicate_routing_key_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        new InboundHandlerRegistry([
            new RegistryTestHandler('stock.reserved'),
            new RegistryTestHandler('stock.reserved'),
        ]);
    }

    #[Test]
    public function an_unknown_key_throws_an_unknown_routing_key_exception(): void
    {
        $registry = new InboundHandlerRegistry([new RegistryTestHandler('stock.reserved')]);

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

    public function handle(object $dto, CarbonImmutable $now): mixed
    {
        return null;
    }
}
