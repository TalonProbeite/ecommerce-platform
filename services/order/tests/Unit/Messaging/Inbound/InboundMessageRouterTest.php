<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Infrastructure\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\Inbound\Exceptions\RetryableMessageException;
use App\Infrastructure\Messaging\Inbound\InboundHandler;
use App\Infrastructure\Messaging\Inbound\InboundHandlerRegistry;
use App\Infrastructure\Messaging\Inbound\InboundMessageRouter;
use App\Infrastructure\Messaging\Inbound\InboundOutcome;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use Carbon\CarbonImmutable;
use Closure;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\TestCase;

final class InboundMessageRouterTest extends TestCase
{
    #[Test]
    public function a_successful_handler_returns_ack(): void
    {
        $router = $this->routerFor($this->handler(
            fn (object $dto, CarbonImmutable $now): mixed => null,
        ));

        self::assertSame(InboundOutcome::Ack, $router->dispatch($this->message()));
    }

    #[Test]
    public function an_unknown_routing_key_returns_reject(): void
    {
        $router = new InboundMessageRouter(
            new InboundHandlerRegistry([]),
            new NullLogger(),
        );

        $outcome = $router->dispatch($this->message('unknown.event'));

        self::assertSame(InboundOutcome::Reject, $outcome);
    }

    #[Test]
    public function broken_json_returns_reject(): void
    {
        $router = $this->routerFor($this->handler());

        self::assertSame(InboundOutcome::Reject, $router->dispatch($this->message(body: '{"event_id":')));
    }

    #[Test]
    public function a_json_array_body_returns_reject(): void
    {
        $router = $this->routerFor($this->handler());

        self::assertSame(InboundOutcome::Reject, $router->dispatch($this->message(body: '["not-an-object"]')));
    }

    #[Test]
    public function unsupported_schema_version_returns_reject(): void
    {
        $router = $this->routerFor($this->handler());

        $body = (string) json_encode([
            'event_id' => '11111111-1111-4111-8111-111111111111',
            'schema_version' => 2,
        ]);

        self::assertSame(InboundOutcome::Reject, $router->dispatch($this->message(body: $body)));
    }

    #[Test]
    public function invalid_payload_from_the_dto_returns_reject(): void
    {
        $router = $this->routerFor(new FakeInboundHandler(
            InboundRoutingKeys::STOCK_RESERVED,
            InvalidInboundDto::class,
            static fn (object $dto, CarbonImmutable $now): mixed => null,
        ));

        self::assertSame(InboundOutcome::Reject, $router->dispatch($this->message()));
    }

    #[Test]
    public function a_retryable_exception_returns_retry(): void
    {
        $router = $this->routerFor($this->handler(
            static fn (object $dto, CarbonImmutable $now): mixed => throw new RetryableMessageException('temporary database failure'),
        ));

        self::assertSame(InboundOutcome::Retry, $router->dispatch($this->message()));
    }

    #[Test]
    public function any_other_throwable_returns_retry(): void
    {
        $router = $this->routerFor($this->handler(
            static fn (object $dto, CarbonImmutable $now): mixed => throw new RuntimeException('unexpected error'),
        ));

        self::assertSame(InboundOutcome::Retry, $router->dispatch($this->message()));
    }

    #[Test]
    public function the_routing_result_is_passed_correctly_to_the_handler(): void
    {
        $called = false;

        $router = $this->routerFor($this->handler(
            function (object $dto, CarbonImmutable $now) use (&$called): mixed {
                self::assertInstanceOf(FakeInboundDto::class, $dto);
                self::assertSame('11111111-1111-4111-8111-111111111111', $dto->eventId);
                $called = true;

                return null;
            },
        ));

        $outcome = $router->dispatch($this->message(body: (string) json_encode([
            'event_id' => '11111111-1111-4111-8111-111111111111',
            'schema_version' => 1,
        ])));

        self::assertSame(InboundOutcome::Ack, $outcome);
        self::assertTrue($called);
    }

    private function routerFor(InboundHandler $handler): InboundMessageRouter
    {
        return new InboundMessageRouter(
            new InboundHandlerRegistry([$handler]),
            new NullLogger(),
        );
    }

    private function handler(?Closure $handle = null): InboundHandler
    {
        return new FakeInboundHandler(
            InboundRoutingKeys::STOCK_RESERVED,
            FakeInboundDto::class,
            $handle ?? static fn (object $dto, CarbonImmutable $now): mixed => null,
        );
    }

    private function message(
        string $routingKey = InboundRoutingKeys::STOCK_RESERVED,
        string $body = '{"event_id":"11111111-1111-4111-8111-111111111111","schema_version":1}',
    ): AMQPMessage {
        $message = new AMQPMessage($body, [
            'content_type' => 'application/json',
            'message_id' => '11111111-1111-4111-8111-111111111111',
        ]);
        $message->setDeliveryInfo(1, false, 'catalog.events', $routingKey);

        return $message;
    }
}

final class FakeInboundHandler implements InboundHandler
{
    private Closure $handle;

    /**
     * @param class-string $dtoClass
     * @param (Closure(object, CarbonImmutable): mixed)|null $handle
     */
    public function __construct(
        private readonly string $routingKey,
        private readonly string $dtoClass,
        ?Closure $handle = null,
    ) {
        $this->handle = $handle ?? static fn (object $dto, CarbonImmutable $now): mixed => null;
    }

    public function routingKey(): string
    {
        return $this->routingKey;
    }

    public function dtoClass(): string
    {
        return $this->dtoClass;
    }

    public function handle(object $dto, CarbonImmutable $now): mixed
    {
        return ($this->handle)($dto, $now);
    }
}

final readonly class FakeInboundDto
{
    public function __construct(public string $eventId)
    {
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self((string) ($payload['event_id'] ?? ''));
    }
}

final readonly class InvalidInboundDto
{
    /**
     * @param array<array-key, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        throw new InvalidMessageException(['event_id' => ['The event id field is required.']]);
    }
}
