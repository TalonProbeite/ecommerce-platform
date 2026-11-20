<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Customer;

use App\Application\Customer\CreateOrder\CreateOrderCommand;
use App\Application\Customer\CreateOrder\CreateOrderItemCommand;
use App\Application\Customer\CreateOrder\CreateOrderUseCase;
use App\Application\Customer\CreateOrder\RequestFingerprint;
use App\Application\Exceptions\IdempotencyFingerprintMismatch;
use App\Application\Exceptions\IdempotencyLockConflict;
use App\Application\Exceptions\ProductUnavailable;
use App\Domain\Catalog\ProductSnapshot;
use App\Domain\Events\OrderCreated;
use App\Domain\Exceptions\MixedStoreOrder;
use App\Domain\Money\Money;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Pricing\PriceCalculator;
use App\Domain\Rental\RentalPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeIdempotencyLock;
use Tests\Support\Fakes\FakeOutboxWriter;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\InMemoryOrderRepository;
use Tests\Support\Fakes\InMemoryProductSnapshotRepository;
use Tests\Support\Fakes\RaceSimulatingOrderRepository;
use Tests\Support\Fakes\SequenceIdGenerator;
use Tests\TestCase;

final class CreateOrderUseCaseTest extends TestCase
{
    private const string CUSTOMER_ID = '11111111-1111-4111-8111-111111111111';
    private const string STORE_ID = '22222222-2222-4222-8222-222222222222';
    private const string OTHER_STORE_ID = '22222222-2222-4222-8222-333333333333';
    private const string PRODUCT_ID = '33333333-3333-4333-8333-333333333333';
    private const string OTHER_PRODUCT_ID = '33333333-3333-4333-8333-444444444444';
    private const string CATEGORY_ID = '44444444-4444-4444-8444-444444444444';
    private const string IDEMPOTENCY_KEY = 'key-1';

    private FakeTransactionRunner $transactions;
    private FakeOutboxWriter $outbox;
    private InMemoryOrderRepository $orders;
    private InMemoryProductSnapshotRepository $snapshots;
    private FakeIdempotencyLock $locks;
    private FixedClock $clock;
    private SequenceIdGenerator $ids;
    private CreateOrderUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactions = new FakeTransactionRunner();
        $this->outbox = new FakeOutboxWriter();
        $this->clock = new FixedClock(CarbonImmutable::parse('2026-11-20T12:00:00.123456+00:00'));
        $this->orders = new InMemoryOrderRepository($this->outbox, $this->clock);
        $this->snapshots = new InMemoryProductSnapshotRepository();
        $this->locks = new FakeIdempotencyLock();
        $this->ids = new SequenceIdGenerator();

        $this->useCase = new CreateOrderUseCase(
            $this->orders,
            $this->snapshots,
            $this->locks,
            $this->transactions,
            $this->ids,
            $this->clock,
            new PriceCalculator(),
        );

        $this->snapshots->add($this->snapshot(self::PRODUCT_ID, self::STORE_ID));
    }

    #[Test]
    public function it_creates_an_order_with_items_journal_and_outbox(): void
    {
        $result = $this->useCase->execute($this->command(quantity: 2));

        self::assertTrue($result->created);
        self::assertSame(OrderStatus::CREATED, $result->order->status());
        self::assertSame(self::CUSTOMER_ID, $result->order->customerId());
        self::assertSame(self::STORE_ID, $result->order->storeId());
        self::assertSame(4000, $result->order->subtotal()->amount());
        self::assertSame(10000, $result->order->deposit()->amount());
        self::assertCount(1, $result->order->items());
        self::assertCount(1, $this->orders->journal());
        self::assertCount(1, $this->outbox->eventsOf(OrderCreated::class));
        self::assertTrue($this->transactions->wasCalled);
    }

    #[Test]
    public function it_releases_the_lock_after_a_successful_request(): void
    {
        $this->useCase->execute($this->command());

        $key = $this->lockKey();

        self::assertSame(30, $this->locks->acquired[$key]);
        self::assertTrue($this->locks->wasReleased($key));
    }

    #[Test]
    public function it_does_not_release_a_lock_with_a_foreign_token(): void
    {
        $key = $this->lockKey();

        self::assertTrue($this->locks->acquire($key, 'owner-token', 30));

        $this->locks->release($key, 'foreign-token');

        self::assertFalse($this->locks->wasReleased($key));
        self::assertTrue($this->locks->isHeld($key));
        self::assertSame('owner-token', $this->locks->tokenFor($key));
    }

    #[Test]
    public function it_replays_an_order_for_the_same_fingerprint(): void
    {
        $first = $this->useCase->execute($this->command());
        $second = $this->useCase->execute($this->command());

        self::assertTrue($first->created);
        self::assertFalse($second->created);
        self::assertSame($first->order->id(), $second->order->id());
        self::assertCount(1, $this->outbox->eventsOf(OrderCreated::class));
    }

    #[Test]
    public function it_rejects_a_different_fingerprint_for_the_same_key(): void
    {
        $this->useCase->execute($this->command(quantity: 1));

        $this->expectException(IdempotencyFingerprintMismatch::class);

        $this->useCase->execute($this->command(quantity: 2));
    }

    #[Test]
    public function it_returns_conflict_when_the_lock_is_not_acquired(): void
    {
        $this->locks->denyAcquire = true;

        $this->expectException(IdempotencyLockConflict::class);

        $this->useCase->execute($this->command());
    }

    #[Test]
    public function it_rejects_a_missing_or_inactive_product(): void
    {
        $this->snapshots->add($this->snapshot(self::OTHER_PRODUCT_ID, self::STORE_ID, isActive: false));

        try {
            $this->useCase->execute($this->command(productId: self::OTHER_PRODUCT_ID));
            self::fail('An unavailable product must be rejected.');
        } catch (ProductUnavailable $exception) {
            self::assertSame(self::OTHER_PRODUCT_ID, $exception->productId);
        }

        self::assertTrue($this->locks->wasReleased($this->lockKey()));
    }

    #[Test]
    public function it_rejects_a_mixed_store_order(): void
    {
        $this->snapshots->add($this->snapshot(self::OTHER_PRODUCT_ID, self::OTHER_STORE_ID));

        $this->expectException(MixedStoreOrder::class);

        $this->useCase->execute($this->commandWithItems([
            $this->item(self::PRODUCT_ID),
            $this->item(self::OTHER_PRODUCT_ID),
        ]));
    }

    #[Test]
    public function it_rereads_and_replays_after_a_unique_key_race(): void
    {
        $command = $this->command();
        $existing = $this->existingOrder($command);
        $orders = new RaceSimulatingOrderRepository($existing);

        $useCase = new CreateOrderUseCase(
            $orders,
            $this->snapshots,
            $this->locks,
            $this->transactions,
            $this->ids,
            $this->clock,
            new PriceCalculator(),
        );

        $result = $useCase->execute($command);

        self::assertFalse($result->created);
        self::assertSame($existing->id(), $result->order->id());
        self::assertTrue($this->locks->wasReleased($this->lockKey()));
    }

    private function command(string $productId = self::PRODUCT_ID, int $quantity = 1): CreateOrderCommand
    {
        return $this->commandWithItems([$this->item($productId, $quantity)]);
    }

    /**
     * @param list<CreateOrderItemCommand> $items
     */
    private function commandWithItems(array $items): CreateOrderCommand
    {
        return new CreateOrderCommand(self::CUSTOMER_ID, self::IDEMPOTENCY_KEY, $items);
    }

    private function item(string $productId = self::PRODUCT_ID, int $quantity = 1): CreateOrderItemCommand
    {
        return new CreateOrderItemCommand(
            productId: $productId,
            quantity: $quantity,
            rentalStartAt: CarbonImmutable::parse('2026-11-21T09:00:00.000000+00:00'),
            rentalEndAt: CarbonImmutable::parse('2026-11-21T11:00:00.000000+00:00'),
        );
    }

    private function snapshot(
        string $productId,
        string $storeId,
        bool $isActive = true,
    ): ProductSnapshot {
        return new ProductSnapshot(
            productId: $productId,
            storeId: $storeId,
            categoryId: self::CATEGORY_ID,
            name: 'Drill',
            pricePerHour: Money::fromMinorUnits(1000),
            depositPerUnit: Money::fromMinorUnits(5000),
            isActive: $isActive,
            sourceOccurredAt: $this->clock->now(),
        );
    }

    private function lockKey(): string
    {
        return 'order:idem:' . self::CUSTOMER_ID . ':' . self::IDEMPOTENCY_KEY;
    }

    private function existingOrder(CreateOrderCommand $command): Order
    {
        $item = OrderItem::create(
            lineId: '01930000-0000-7000-8000-000000000999',
            storeId: self::STORE_ID,
            productId: self::PRODUCT_ID,
            productName: 'Drill',
            quantity: 1,
            rentalPeriod: new RentalPeriod(
                CarbonImmutable::parse('2026-11-21T09:00:00.000000+00:00'),
                CarbonImmutable::parse('2026-11-21T11:00:00.000000+00:00'),
            ),
            pricePerHour: Money::fromMinorUnits(1000),
            depositPerUnit: Money::fromMinorUnits(5000),
        );

        return Order::create(
            id: '01930000-0000-7000-8000-000000000998',
            customerId: self::CUSTOMER_ID,
            storeId: self::STORE_ID,
            items: [$item],
            idempotencyKey: self::IDEMPOTENCY_KEY,
            requestFingerprint: RequestFingerprint::for($command),
            createdAt: $this->clock->now(),
        );
    }
}
