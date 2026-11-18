<?php

declare(strict_types=1);

namespace Tests\Unit\Application\OverdueRentals;

use App\Application\Exceptions\ForbiddenException;
use App\Application\Exceptions\InvalidCursor;
use App\Application\Query\OverdueRentals\ListOverdueRentalsUseCase;
use App\Application\Query\OverdueRentals\OverdueRentalCursor;
use App\Application\Query\OverdueRentals\OverdueRentalItem;
use App\Application\Query\OverdueRentals\OverdueRentalPage;
use App\Enums\Role;
use App\Http\Auth\AuthUser;
use App\Policies\OrderPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\InMemoryOverdueRentalReadRepository;
use Tests\TestCase;

final class ListOverdueRentalsUseCaseTest extends TestCase
{
    private const string NOW = '2026-12-02T12:00:00.123456+00:00';

    private const string ADMIN_ID = '99999999-9999-4999-8999-999999999999';

    private const string STORE_ID = '22222222-2222-4222-8222-222222222222';

    private FixedClock $clock;

    private InMemoryOverdueRentalReadRepository $repository;

    private ListOverdueRentalsUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new FixedClock(CarbonImmutable::parse(self::NOW));
        $this->repository = new InMemoryOverdueRentalReadRepository();
        $this->useCase = new ListOverdueRentalsUseCase(
            $this->repository,
            $this->clock,
            new OrderPolicy(),
        );
    }

    #[Test]
    public function it_returns_overdue_items_and_passes_the_clock_time_to_the_criteria(): void
    {
        $this->repository->setPage(new OverdueRentalPage([
            $this->item('2026-12-02T11:00:00.123456+00:00'),
        ], null));

        $page = $this->useCase->execute($this->admin(), null, null, 20);

        self::assertCount(1, $page->items);
        self::assertSame('order-1', $page->items[0]->orderId);
        self::assertNotNull($this->repository->criteria);
        self::assertTrue($this->repository->criteria->now->equalTo(CarbonImmutable::parse(self::NOW)));
        self::assertNull($this->repository->criteria->storeId);
        self::assertSame(20, $this->repository->limit);
        self::assertNull($this->repository->cursor);
    }

    #[Test]
    public function it_calculates_overdue_hours_rounding_up(): void
    {
        $this->repository->setPage(new OverdueRentalPage([
            $this->item('2026-12-02T11:00:00.123456+00:00'),
            $this->item('2026-12-02T10:30:00.123456+00:00'),
            $this->item('2026-12-02T11:59:59.123456+00:00'),
        ], null));

        $page = $this->useCase->execute($this->admin(), null, null, 20);

        self::assertSame(1, $page->items[0]->overdueHours);
        self::assertSame(2, $page->items[1]->overdueHours);
        self::assertSame(1, $page->items[2]->overdueHours);
    }

    #[Test]
    public function an_item_that_ends_exactly_now_is_not_overdue(): void
    {
        $this->repository->setPage(new OverdueRentalPage([
            $this->item(self::NOW),
        ], null));

        $page = $this->useCase->execute($this->admin(), null, null, 20);

        self::assertSame(0, $page->items[0]->overdueHours);
    }

    #[Test]
    public function it_encodes_and_decodes_the_cursor_round_trip(): void
    {
        $cursor = new OverdueRentalCursor(
            CarbonImmutable::parse('2026-12-02T10:30:00.654321+00:00'),
            '01930000-0000-7000-8000-000000000301',
        );

        $decoded = OverdueRentalCursor::decode($cursor->encode());

        self::assertNotNull($decoded);
        self::assertTrue($decoded->rentalEndAt->equalTo($cursor->rentalEndAt));
        self::assertSame($cursor->id, $decoded->id);
    }

    #[Test]
    public function it_rejects_a_tampered_cursor(): void
    {
        $tampered = rtrim(strtr(base64_encode('{"rental_end_at":"not-a-date","id":"x"}'), '+/', '-_'), '=');

        self::assertNull(OverdueRentalCursor::decode($tampered));
        self::assertNull(OverdueRentalCursor::decode('%%%'));

        $this->expectException(InvalidCursor::class);

        $this->useCase->execute($this->admin(), null, $tampered, 20);
    }

    #[Test]
    public function it_forwards_a_valid_cursor_to_the_repository(): void
    {
        $cursor = (new OverdueRentalCursor(
            CarbonImmutable::parse('2026-12-02T10:30:00.654321+00:00'),
            '01930000-0000-7000-8000-000000000301',
        ))->encode();

        $this->useCase->execute($this->admin(), null, $cursor, 20);

        self::assertSame($cursor, $this->repository->cursor);
    }

    #[Test]
    public function it_passes_the_store_filter_to_the_criteria(): void
    {
        $this->useCase->execute($this->admin(), self::STORE_ID, null, 20);

        self::assertNotNull($this->repository->criteria);
        self::assertSame(self::STORE_ID, $this->repository->criteria->storeId);
    }

    #[Test]
    public function it_rejects_a_limit_outside_the_allowed_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->useCase->execute($this->admin(), null, null, 101);
    }

    #[Test]
    public function it_rejects_a_zero_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->useCase->execute($this->admin(), null, null, 0);
    }

    #[Test]
    public function it_forbids_a_customer(): void
    {
        $this->expectException(ForbiddenException::class);

        $this->useCase->execute(
            new AuthUser('11111111-1111-4111-8111-111111111111', Role::CUSTOMER),
            null,
            null,
            20,
        );
    }

    #[Test]
    public function it_forbids_a_store_manager(): void
    {
        $this->expectException(ForbiddenException::class);

        $this->useCase->execute(
            new AuthUser('11111111-1111-4111-8111-222222222222', Role::STORE_MANAGER, self::STORE_ID),
            null,
            null,
            20,
        );
    }

    private function admin(): AuthUser
    {
        return new AuthUser(self::ADMIN_ID, Role::ADMIN);
    }

    private function item(string $rentalEndAt): OverdueRentalItem
    {
        return new OverdueRentalItem(
            orderId: 'order-1',
            itemId: 'item-1',
            productId: '33333333-3333-4333-8333-333333333333',
            productName: 'Drill',
            storeId: self::STORE_ID,
            customerId: '11111111-1111-4111-8111-111111111111',
            quantity: 1,
            rentalEndAt: CarbonImmutable::parse($rentalEndAt),
            overdueHours: 0,
        );
    }
}
