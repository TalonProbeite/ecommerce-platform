<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Application\Ports\Clock;
use App\Application\Ports\IdempotencyLock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\OrderReadRepository;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\ProductSnapshotRepository;
use App\Application\Ports\TransactionRunnerInterface;
use App\Domain\Catalog\ProductSnapshot;
use App\Domain\Money\Money;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Rental\RentalPeriod;
use App\Enums\Role;
use Carbon\CarbonImmutable;
use Tests\Support\Fakes\FakeIdempotencyLock;
use Tests\Support\Fakes\FakeOutboxWriter;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\InMemoryOrderReadRepository;
use Tests\Support\Fakes\InMemoryOrderRepository;
use Tests\Support\Fakes\InMemoryProductSnapshotRepository;
use Tests\Support\Fakes\SequenceIdGenerator;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    protected const string CUSTOMER_ID = '11111111-1111-4111-8111-111111111111';
    protected const string OTHER_CUSTOMER_ID = '11111111-1111-4111-8111-222222222222';
    protected const string STORE_ID = '22222222-2222-4222-8222-222222222222';
    protected const string OTHER_STORE_ID = '22222222-2222-4222-8222-333333333333';
    protected const string PRODUCT_ID = '33333333-3333-4333-8333-333333333333';
    protected const string OTHER_PRODUCT_ID = '33333333-3333-4333-8333-444444444444';
    protected const string CATEGORY_ID = '44444444-4444-4444-8444-444444444444';

    protected FixedClock $clock;

    protected FakeOutboxWriter $outbox;

    protected InMemoryOrderRepository $orders;

    protected InMemoryProductSnapshotRepository $snapshots;

    protected InMemoryOrderReadRepository $readOrders;

    protected FakeIdempotencyLock $locks;

    private \OpenSSLAsymmetricKey $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        self::assertNotFalse($privateKey);

        $details = openssl_pkey_get_details($privateKey);

        self::assertIsArray($details);
        self::assertIsString($details['key'] ?? null);

        $this->privateKey = $privateKey;

        config([
            'jwt.public_key_b64' => base64_encode($details['key']),
            'jwt.algo' => 'RS256',
        ]);

        $this->clock = new FixedClock(CarbonImmutable::parse('2026-11-20T12:00:00.123456+00:00'));
        $this->outbox = new FakeOutboxWriter();
        $this->orders = new InMemoryOrderRepository($this->outbox, $this->clock);
        $this->snapshots = new InMemoryProductSnapshotRepository();
        $this->readOrders = new InMemoryOrderReadRepository();
        $this->locks = new FakeIdempotencyLock();

        $this->app->instance(Clock::class, $this->clock);
        $this->app->instance(OrderRepository::class, $this->orders);
        $this->app->instance(OrderReadRepository::class, $this->readOrders);
        $this->app->instance(ProductSnapshotRepository::class, $this->snapshots);
        $this->app->instance(IdempotencyLock::class, $this->locks);
        $this->app->instance(TransactionRunnerInterface::class, new FakeTransactionRunner());
        $this->app->instance(IdGenerator::class, new SequenceIdGenerator());
    }

    protected function createProduct(
        string $productId = self::PRODUCT_ID,
        string $storeId = self::STORE_ID,
        bool $isActive = true,
        ?CarbonImmutable $deletedAt = null,
    ): void {
        $this->snapshots->add(new ProductSnapshot(
            productId: $productId,
            storeId: $storeId,
            categoryId: self::CATEGORY_ID,
            name: 'Drill',
            pricePerHour: Money::fromMinorUnits(1000),
            depositPerUnit: Money::fromMinorUnits(5000),
            isActive: $isActive,
            sourceOccurredAt: $this->clock->now(),
            deletedAt: $deletedAt,
        ));
    }

    protected function seedOrder(
        string $id,
        string $customerId,
        string $storeId,
        OrderStatus $status = OrderStatus::CREATED,
        ?CarbonImmutable $createdAt = null,
    ): Order {
        $createdAt ??= $this->clock->now();
        $item = $this->orderItem($id, $storeId);

        return Order::restore(
            id: $id,
            customerId: $customerId,
            storeId: $storeId,
            status: $status,
            cancelReason: null,
            items: [$item],
            subtotal: $item->lineTotal(),
            deposit: $item->depositTotal(),
            version: 1,
            idempotencyKey: 'seed-' . $id,
            requestFingerprint: hash('sha256', 'seed-' . $id),
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }

    protected function persistNewOrder(
        string $id,
        string $customerId,
        string $storeId,
    ): Order {
        $order = Order::create(
            id: $id,
            customerId: $customerId,
            storeId: $storeId,
            items: [$this->orderItem($id, $storeId)],
            idempotencyKey: 'persist-' . $id,
            requestFingerprint: hash('sha256', 'persist-' . $id),
            createdAt: $this->clock->now(),
        );

        $this->orders->save($order);
        $this->readOrders->add($order);

        return $order;
    }

    protected function tokenFor(
        Role $role,
        ?string $storeId = null,
        ?string $userId = null,
        array $extraClaims = [],
        ?int $expiresAt = null,
    ): string {
        $claims = array_merge([
            'user_id' => $userId ?? self::CUSTOMER_ID,
            'role' => $role->value,
        ], $extraClaims);

        if ($storeId !== null && ! array_key_exists('store_id', $claims)) {
            $claims['store_id'] = $storeId;
        }

        return $this->token($claims, $expiresAt);
    }

    /**
     * @param array<string, mixed> $claims
     */
    protected function token(array $claims, ?int $expiresAt = null): string
    {
        $now = time();

        $payload = array_merge([
            'iat' => $now,
            'exp' => $expiresAt ?? $now + 3600,
        ], $claims);

        $header = $this->encodeJson(['alg' => 'RS256', 'typ' => 'JWT']);
        $body = $this->encodeJson($payload);
        $signature = '';

        openssl_sign($header . '.' . $body, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return $header . '.' . $body . '.' . $this->encode($signature);
    }

    /**
     * @return array<string, string>
     */
    protected function headers(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    private function encodeJson(mixed $value): string
    {
        return $this->encode(json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function orderItem(string $seed, string $storeId): OrderItem
    {
        return OrderItem::create(
            lineId: $this->lineId($seed),
            storeId: $storeId,
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
    }

    private function lineId(string $seed): string
    {
        return sprintf('01930000-0000-7000-8000-%012d', abs(crc32($seed)));
    }
}
