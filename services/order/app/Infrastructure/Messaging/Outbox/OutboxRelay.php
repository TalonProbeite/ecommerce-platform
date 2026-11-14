<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Outbox;

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
            $maxAttempts = (int) config('outbox.max_attempts', 10);
            $rows = $this->repository->lockPendingBatch($batchSize);
            $publishedCount = 0;
            /** @var array<string, true> $blockedAggregates */
            $blockedAggregates = [];

            foreach ($rows as $row) {
                if ($row->aggregateKey !== null && isset($blockedAggregates[$row->aggregateKey])) {
                    continue;
                }

                $body = [
                    'event_id' => $row->eventId,
                    'schema_version' => 1,
                    'occurred_at' => $row->occurredAt->utc()->format('Y-m-d\TH:i:s.uP'),
                    ...$row->payload,
                ];

                try {
                    $this->publisher->publish($row->routingKey, $body, $row->eventId);
                    $this->repository->markPublished($row->id, CarbonImmutable::now());
                    $publishedCount++;
                } catch (Throwable $e) {
                    $quarantined = $this->repository->recordFailure($row, $e->getMessage(), $maxAttempts);

                    if ($row->aggregateKey !== null) {
                        $blockedAggregates[$row->aggregateKey] = true;
                    }

                    $context = [
                        'event_id' => $row->eventId,
                        'routing_key' => $row->routingKey,
                        'aggregate_key' => $row->aggregateKey,
                        'attempts' => $row->attempts + 1,
                        'exception' => $e,
                    ];

                    if ($quarantined) {
                        $this->logger->critical(sprintf(
                            'Outbox event %s (%s) quarantined in outbox_failed after %d attempts: %s',
                            $row->eventId,
                            $row->routingKey,
                            $row->attempts + 1,
                            $e->getMessage(),
                        ), $context);

                        continue;
                    }

                    $this->logger->error(sprintf(
                        'Failed to publish outbox event %s (%s): %s',
                        $row->eventId,
                        $row->routingKey,
                        $e->getMessage(),
                    ), $context);

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
