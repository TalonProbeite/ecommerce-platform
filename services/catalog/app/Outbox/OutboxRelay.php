<?php

declare(strict_types=1);

namespace App\Outbox;

use App\Contracts\Outbox\OutboxMessagePublisherInterface;
use App\Contracts\Outbox\OutboxRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class OutboxRelay
{
    public function __construct(
        private OutboxRepositoryInterface $repository,
        private OutboxMessagePublisherInterface $publisher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Process a single batch of unpublished outbox messages.
     *
     * @return int Number of successfully published messages.
     */
    public function processBatch(): int
    {
        DB::beginTransaction();

        try {
            $batchSize = (int) config('outbox.batch_size', 100);
            $rows = $this->repository->lockPendingBatch($batchSize);
            $publishedCount = 0;

            foreach ($rows as $row) {
                $body = [
                    'event_id' => $row->eventId,
                    'occurred_at' => $row->occurredAt->toIso8601String(),
                    ...$row->payload,
                ];

                try {
                    $this->publisher->publish($row->routingKey, $body, $row->eventId);
                    $this->repository->markPublished($row->id, CarbonImmutable::now());
                    $publishedCount++;
                } catch (Throwable $e) {
                    $this->logger->error(sprintf(
                        'Failed to publish outbox event %s (%s): %s',
                        $row->eventId,
                        $row->routingKey,
                        $e->getMessage(),
                    ), [
                        'event_id' => $row->eventId,
                        'routing_key' => $row->routingKey,
                        'exception' => $e,
                    ]);

                    $this->repository->recordFailure($row->id, $e->getMessage());
                    continue;
                }
            }

            DB::commit();

            return $publishedCount;
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $e;
        }
    }
}
