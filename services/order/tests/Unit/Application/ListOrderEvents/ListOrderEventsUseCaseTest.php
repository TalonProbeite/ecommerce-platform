<?php

declare(strict_types=1);

namespace Tests\Unit\Application\ListOrderEvents;

use App\Application\Exceptions\ForbiddenException;
use App\Application\Exceptions\InvalidCursor;
use App\Application\Exceptions\OrderNotFound;
use App\Application\Query\ListOrderEvents\ListOrderEventsUseCase;
use App\Application\Query\ListOrderEvents\OrderEventCursor;
use App\Application\Query\ListOrderEvents\OrderEventItem;
use App\Enums\Role;
use App\Http\Auth\AuthUser;
use App\Policies\OrderPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\InMemoryOrderEventReadRepository;
use Tests\TestCase;

final class ListOrderEventsUseCaseTest extends TestCase
{
    private const string ORDER_ID = '77777777-7777-4777-8777-777777777771';

    private const string ADMIN_ID = '99999999-9999-4999-8999-999999999999';

    private InMemoryOrderEventReadRepository $repository;

    private ListOrderEventsUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InMemoryOrderEventReadRepository();
        $this->useCase = new ListOrderEventsUseCase($this->repository, new OrderPolicy());
    }

    #[Test]
    public function it_returns_events_and_forwards_the_cursor_and_limit(): void
    {
        $this->repository->addEvent($this->event('1', '2026-11-20T10:00:00.123456+00:00'), self::ORDER_ID);

        $cursor = (new OrderEventCursor(
            CarbonImmutable::parse('2026-11-20T09:00:00.000000+00:00'),
            '12',
        ))->encode();

        $page = $this->useCase->execute($this->admin(), self::ORDER_ID, $cursor, 25);

        self::assertCount(1, $page->events);
        self::assertNotNull($this->repository->criteria);
        self::assertSame(self::ORDER_ID, $this->repository->criteria->orderId);
        self::assertSame(25, $this->repository->criteria->limit);
        self::assertNotNull($this->repository->criteria->cursor);
        self::assertSame('12', $this->repository->criteria->cursor->id);
    }

    #[Test]
    public function it_treats_an_empty_cursor_as_no_cursor(): void
    {
        $this->repository->addOrder(self::ORDER_ID);

        $this->useCase->execute($this->admin(), self::ORDER_ID, '', 50);

        self::assertNotNull($this->repository->criteria);
        self::assertNull($this->repository->criteria->cursor);
    }

    #[Test]
    public function it_rejects_an_invalid_cursor(): void
    {
        $this->expectException(InvalidCursor::class);

        $this->useCase->execute($this->admin(), self::ORDER_ID, 'not-a-cursor', 50);
    }

    #[Test]
    public function it_rejects_a_limit_outside_the_allowed_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->useCase->execute($this->admin(), self::ORDER_ID, null, 101);
    }

    #[Test]
    public function it_rejects_a_zero_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->useCase->execute($this->admin(), self::ORDER_ID, null, 0);
    }

    #[Test]
    public function it_returns_not_found_for_a_missing_order(): void
    {
        $this->expectException(OrderNotFound::class);

        $this->useCase->execute($this->admin(), self::ORDER_ID, null, 50);
    }

    #[Test]
    public function it_forbids_a_customer(): void
    {
        $this->expectException(ForbiddenException::class);

        $this->useCase->execute(
            new AuthUser('11111111-1111-4111-8111-111111111111', Role::CUSTOMER),
            self::ORDER_ID,
            null,
            50,
        );
    }

    #[Test]
    public function it_forbids_a_store_manager(): void
    {
        $this->expectException(ForbiddenException::class);

        $this->useCase->execute(
            new AuthUser('11111111-1111-4111-8111-222222222222', Role::STORE_MANAGER, '22222222-2222-4222-8222-222222222222'),
            self::ORDER_ID,
            null,
            50,
        );
    }

    #[Test]
    public function it_exposes_the_documented_default_limit(): void
    {
        self::assertSame(50, ListOrderEventsUseCase::DEFAULT_LIMIT);
    }

    #[Test]
    public function the_cursor_round_trips_and_rejects_tampering(): void
    {
        $cursor = new OrderEventCursor(
            CarbonImmutable::parse('2026-11-20T10:00:00.654321+00:00'),
            '42',
        );

        $decoded = OrderEventCursor::decode($cursor->encode());

        self::assertNotNull($decoded);
        self::assertSame('42', $decoded->id);
        self::assertTrue($decoded->occurredAt->equalTo($cursor->occurredAt));
        self::assertNull(OrderEventCursor::decode('%%%'));
        self::assertNull(OrderEventCursor::decode(base64_encode('{"occurred_at":"bad","id":"1"}')));
        self::assertNull(OrderEventCursor::decode(base64_encode('{"occurred_at":"2026-11-20T10:00:00.000000+00:00","id":"x"}'))); 
    }

    private function admin(): AuthUser
    {
        return new AuthUser(self::ADMIN_ID, Role::ADMIN);
    }

    private function event(string $id, string $occurredAt, ?string $orderItemId = null): OrderEventItem
    {
        return new OrderEventItem(
            id: $id,
            fromStatus: 'created',
            toStatus: 'stock_reserved',
            orderItemId: $orderItemId,
            actorRole: 'system',
            occurredAt: CarbonImmutable::parse($occurredAt),
        );
    }
}
