<?php

declare(strict_types=1);

namespace App\Application\Saga\ExpireUnpaidOrders;

use App\Application\Customer\CancelOrder\CancelOrder;
use App\Application\Ports\Clock;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\TransactionRunnerInterface;
use App\Domain\Order\CancelReason;
use App\Domain\Order\Order;
use App\Domain\Order\OrderStatus;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ExpireUnpaidOrders
{
    public const int BATCH_SIZE = 50;
    public const int TIMEOUT_MINUTES = 30;

    public function __construct(
        private TransactionRunnerInterface $transactions,
        private OrderRepository $orders,
        private CancelOrder $cancelOrder,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(): int
    {
        $now = $this->clock->now();
        $cutoff = $now->subMinutes(self::TIMEOUT_MINUTES);

        $expired = $this->transactions->run(
            fn () => $this->lockExpired($cutoff),
        );

        $cancelled = 0;
        $failed = 0;

        foreach ($expired as $order) {
            try {
                $result = $this->cancelOrder->executeAsSystem(
                    $order->id(),
                    CancelReason::PAYMENT_TIMEOUT,
                    onlyIfUnpaid: true,
                );

                if ($result->changed) {
                    $cancelled++;
                } elseif ($result->order->status() === OrderStatus::PAID) {
                    $this->logger->info('Skipped expiring an order that was paid after selection.', [
                        'order_id' => $order->id(),
                    ]);
                }
            } catch (Throwable $exception) {
                $failed++;

                $this->logger->error('Failed to expire an unpaid order.', [
                    'order_id' => $order->id(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->logger->info('Processed an unpaid order expiration batch.', [
            'selected' => count($expired),
            'cancelled' => $cancelled,
            'failed' => $failed,
        ]);

        return $cancelled;
    }

    /**
     * @return list<Order>
     */
    private function lockExpired(CarbonImmutable $cutoff): array
    {
        return $this->orders->lockExpiredUnpaidForUpdate($cutoff, self::BATCH_SIZE);
    }
}
