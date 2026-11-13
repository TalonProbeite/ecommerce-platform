<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Messaging\Inbound\Exceptions\RetryableMessageException;
use App\Messaging\Inbound\InboundHandler;
use App\Messaging\Inbound\InboundHandlerRegistry;
use App\Messaging\Inbound\InboundMessageRouter;
use App\Messaging\Inbound\InboundOutcome;
use App\Messaging\Inbound\InboundRoutingKeys;
use App\Services\Inbound\Results\ProcessingResult;
use App\Services\Inbound\Results\StockRejectionReason;
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
            fn (object $dto, CarbonImmutable $now): ProcessingResult => ProcessingResult::applied(),
        ));

        self::assertSame(InboundOutcome::Ack, $router->dispatch($this->message()));
    }

    #[Test]
    public function a_rejected_processing_result_returns_ack(): void
    {
        $router = $this->routerFor($this->handler(
            fn (object $dto, CarbonImmutable $now): ProcessingResult => ProcessingResult::rejected(
                StockRejectionReason::InsufficientStock,
            ),
        ));

        self::assertSame(InboundOutcome::Ack, $router->dispatch($this->message()));
    }

    #[Test]
    public function a_duplicate_processing_result_returns_ack(): void
    {
        $router = $this->routerFor($this->handler(
            fn (object $dto, CarbonImmutable $now): ProcessingResult => ProcessingResult::duplicate(),
        ));

        self::assertSame(InboundOutcome::Ack, $router->dispatch($this->message()));
    }

    #[Test]
    public function an_unknown_routing_key_returns_reject(): void
    {
        $router = new InboundMessageRouter(new InboundHandlerRegistry([]), new NullLogger());

        $outcome = $router->dispatch($this->message('order.completed'));

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
    public function invalid_payload_from_the_dto_returns_reject(): void
    {
        $router = $this->routerFor(new FakeInboundHandler(
            InboundRoutingKeys::ORDER_CREATED,
            InvalidInboundDto::class,
            static fn (object $dto, CarbonImmutable $now): ProcessingResult => ProcessingResult::applied(),
        ));

        self::assertSame(InboundOutcome::Reject, $router->dispatch($this->message()));
    }

    #[Test]
    public function a_retryable_exception_returns_retry(): void
    {
        $router = $this->routerFor($this->handler(
            static fn (object $dto, CarbonImmutable $now): ProcessingResult => throw new RetryableMessageException('temporary'),
        ));

        self::assertSame(InboundOutcome::Retry, $router->dispatch($this->message()));
    }

    #[Test]
    public function any_other_throwable_returns_retry(): void
    {
        $router = $this->routerFor($this->handler(
            static fn (object $dto, CarbonImmutable $now): ProcessingResult => throw new RuntimeException('boom'),
        ));

        self::assertSame(InboundOutcome::Retry, $router->dispatch($this->message()));
    }

    #[Test]
    public function the_routing_result_status_is_preserved_in_the_dto(): void
    {
        $router = $this->routerFor($this->handler(
            static function (object $dto, CarbonImmutable $now): ProcessingResult {
                self::assertInstanceOf(FakeInboundDto::class, $dto);
                self::assertSame('11111111-1111-4111-8111-111111111111', $dto->eventId);

                return ProcessingResult::applied();
            },
        ));

        self::assertSame(
            InboundOutcome::Ack,
            $router->dispatch($this->message(body: (string) json_encode([
                'event_id' => '11111111-1111-4111-8111-111111111111',
            ]))),
        );
    }

    private function routerFor(InboundHandler $handler): InboundMessageRouter
    {
        return new InboundMessageRouter(new InboundHandlerRegistry([$handler]), new NullLogger());
    }

    private function handler(?Closure $handle = null): InboundHandler
    {
        return new FakeInboundHandler(
            InboundRoutingKeys::ORDER_CREATED,
            FakeInboundDto::class,
            $handle ?? static fn (object $dto, CarbonImmutable $now): ProcessingResult => ProcessingResult::applied(),
        );
    }

    private function message(
        string $routingKey = InboundRoutingKeys::ORDER_CREATED,
        string $body = '{"event_id":"11111111-1111-4111-8111-111111111111"}',
    ): AMQPMessage {
        $message = new AMQPMessage($body, [
            'content_type' => 'application/json',
            'message_id' => '11111111-1111-4111-8111-111111111111',
        ]);
        $message->setDeliveryInfo(1, false, 'order.events', $routingKey);

        return $message;
    }
}

final class FakeInboundHandler implements InboundHandler
{
    private Closure $handle;

    /**
     * @param class-string $dtoClass
     * @param (Closure(object, CarbonImmutable): ProcessingResult)|null $handle
     */
    public function __construct(
        private readonly string $routingKey,
        private readonly string $dtoClass,
        ?Closure $handle = null,
    ) {
        $this->handle = $handle ?? static fn (object $dto, CarbonImmutable $now): ProcessingResult => ProcessingResult::applied();
    }

    public function routingKey(): string
    {
        return $this->routingKey;
    }

    public function dtoClass(): string
    {
        return $this->dtoClass;
    }

    public function handle(object $dto, CarbonImmutable $now): ProcessingResult
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
        return new self((string) $payload['event_id']);
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
