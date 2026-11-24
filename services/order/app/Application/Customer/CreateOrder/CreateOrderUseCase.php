<?php

declare(strict_types=1);

namespace App\Application\Customer\CreateOrder;

use App\Application\Exceptions\IdempotencyFingerprintMismatch;
use App\Application\Exceptions\IdempotencyKeyAlreadyExists;
use App\Application\Exceptions\IdempotencyLockConflict;
use App\Application\Exceptions\ProductUnavailable;
use App\Application\Ports\Clock;
use App\Application\Ports\IdempotencyLock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\ProductSnapshotRepository;
use App\Application\Ports\TransactionRunnerInterface;
use App\Domain\Catalog\ProductSnapshot;
use App\Domain\Exceptions\MixedStoreOrder;
use App\Domain\Order\Order;
use App\Domain\Pricing\PriceCalculator;
use App\Domain\Pricing\PriceLine;
use App\Domain\Rental\RentalPeriod;
use Illuminate\Support\Str;

final readonly class CreateOrderUseCase
{
    private const int LOCK_TTL_SECONDS = 30;

    public function __construct(
        private OrderRepository $orders,
        private ProductSnapshotRepository $snapshots,
        private IdempotencyLock $locks,
        private TransactionRunnerInterface $transactions,
        private IdGenerator $ids,
        private Clock $clock,
        private PriceCalculator $pricing,
    ) {
    }

    public function execute(CreateOrderCommand $command): CreateOrderResult
    {
        $fingerprint = RequestFingerprint::for($command);
        $lockKey = $this->lockKey($command);
        $lockToken = (string) Str::uuid7();

        if (! $this->locks->acquire($lockKey, $lockToken, self::LOCK_TTL_SECONDS)) {
            throw new IdempotencyLockConflict($command->idempotencyKey);
        }

        try {
            try {
                return $this->transactions->run(
                    fn (): CreateOrderResult => $this->createOrReplay($command, $fingerprint),
                );
            } catch (IdempotencyKeyAlreadyExists $exception) {
                return $this->transactions->run(function () use ($command, $fingerprint, $exception): CreateOrderResult {
                    $existing = $this->orders->findByIdempotencyKey(
                        $command->customerId,
                        $command->idempotencyKey,
                    );

                    if ($existing === null) {
                        throw $exception;
                    }

                    return $this->replay($existing, $fingerprint);
                });
            }
        } finally {
            $this->locks->release($lockKey, $lockToken);
        }
    }

    private function createOrReplay(CreateOrderCommand $command, string $fingerprint): CreateOrderResult
    {
        $existing = $this->orders->findByIdempotencyKey(
            $command->customerId,
            $command->idempotencyKey,
        );

        if ($existing !== null) {
            return $this->replay($existing, $fingerprint);
        }

        $snapshots = $this->loadSnapshots($command);
        $lines = [];

        foreach ($command->items as $item) {
            $snapshot = $snapshots[$item->productId];

            $lines[] = new PriceLine(
                storeId: $snapshot->storeId,
                productId: $snapshot->productId,
                productName: $snapshot->name,
                pricePerHour: $snapshot->pricePerHour,
                depositPerUnit: $snapshot->depositPerUnit,
                quantity: $item->quantity,
                rentalPeriod: new RentalPeriod($item->rentalStartAt, $item->rentalEndAt),
            );
        }

        $storeIds = array_values(array_unique(array_map(
            static fn (PriceLine $line): string => $line->storeId,
            $lines,
        )));

        if (count($storeIds) > 1) {
            throw MixedStoreOrder::forOrder('new order', $storeIds);
        }

        $calculation = $this->pricing->calculate($lines);
        $orderItems = [];

        foreach ($calculation->items as $pricedItem) {
            $orderItems[] = $pricedItem->toOrderItem($this->ids->next());
        }

        $order = Order::create(
            id: $this->ids->next(),
            customerId: $command->customerId,
            storeId: $storeIds[0],
            items: $orderItems,
            idempotencyKey: $command->idempotencyKey,
            requestFingerprint: $fingerprint,
            createdAt: $this->clock->now(),
        );

        $this->orders->save($order);

        return new CreateOrderResult($order, true);
    }

    private function replay(Order $order, string $fingerprint): CreateOrderResult
    {
        if (! hash_equals($order->requestFingerprint(), $fingerprint)) {
            throw new IdempotencyFingerprintMismatch($order->idempotencyKey());
        }

        return new CreateOrderResult($order, false);
    }

    /**
     * @return array<string, ProductSnapshot>
     */
    private function loadSnapshots(CreateOrderCommand $command): array
    {
        $productIds = array_values(array_unique(array_map(
            static fn (CreateOrderItemCommand $item): string => $item->productId,
            $command->items,
        )));

        $snapshots = $this->snapshots->findActiveByIds($productIds);

        foreach ($productIds as $productId) {
            if (! isset($snapshots[$productId]) || ! $snapshots[$productId]->isAvailable()) {
                throw new ProductUnavailable($productId);
            }
        }

        return $snapshots;
    }

    private function lockKey(CreateOrderCommand $command): string
    {
        return sprintf(
            'order:idem:%s:%s',
            $command->customerId,
            $command->idempotencyKey,
        );
    }
}
