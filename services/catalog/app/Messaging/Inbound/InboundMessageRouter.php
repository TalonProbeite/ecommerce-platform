<?php

declare(strict_types=1);

namespace App\Messaging\Inbound;

use App\Infrastructure\Cache\ProductCache;
use App\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Messaging\Inbound\Exceptions\InvalidReservationTransitionException;
use App\Messaging\Inbound\Exceptions\RetryableMessageException;
use App\Messaging\Inbound\Exceptions\UnknownRoutingKeyException;
use App\Services\Inbound\Results\ProcessingStatus;
use Carbon\CarbonImmutable;
use JsonException;
use LogicException;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use stdClass;
use Throwable;

final readonly class InboundMessageRouter
{
    public function __construct(
        private InboundHandlerRegistry $registry,
        private LoggerInterface $logger,
        private ProductCache $productCache,
    ) {
    }

    public function dispatch(AMQPMessage $message): InboundOutcome
    {
        $routingKey = $message->getRoutingKey() ?? '';
        $messageId = $message->has('message_id') ? (string) $message->get('message_id') : null;
        $eventId = null;

        try {
            // 1. Find handler by routing key. Missing -> UnknownRoutingKeyException -> Reject.
            $handler = $this->registry->get($routingKey);

            // 2. Decode body. Error or not a JSON object -> InvalidMessageException -> Reject.
            $rawBody = $message->getBody();
            try {
                $decoded = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new InvalidMessageException([], sprintf('Failed to decode JSON: %s', $e->getMessage()));
            }

            if (! $decoded instanceof stdClass) {
                throw new InvalidMessageException([], 'Message body must be a JSON object.');
            }

            try {
                /** @var array<array-key, mixed> $payload */
                $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new InvalidMessageException([], sprintf('Failed to decode JSON payload: %s', $e->getMessage()));
            }

            if (isset($payload['event_id']) && is_string($payload['event_id'])) {
                $eventId = $payload['event_id'];
            }

            // 3. handler->dtoClass()::fromPayload(payload). Validation failure -> Reject (InvalidMessageException).
            $dtoClass = $handler->dtoClass();
            if (! method_exists($dtoClass, 'fromPayload')) {
                throw new LogicException(sprintf('Class "%s" must define static fromPayload(array $payload).', $dtoClass));
            }

            /** @var object $dto */
            $dto = $dtoClass::fromPayload($payload);

            if (property_exists($dto, 'eventId') && is_string($dto->eventId)) {
                $eventId = $dto->eventId;
            }

            // 4. handler->handle(dto, CarbonImmutable::now()). Returns ProcessingResult -> Ack.
            $now = CarbonImmutable::now();
            $result = $handler->handle($dto, $now);

            if ($result->status === ProcessingStatus::Applied) {
                $this->invalidateProductCards($result->affectedProductIds, $routingKey, $messageId, $eventId);
            }

            $this->logger->info(
                'Inbound message handled successfully.',
                $this->buildLogContext($routingKey, $messageId, $eventId, [
                    'status' => $result->status->value,
                ]),
            );

            return InboundOutcome::Ack;
        } catch (InvalidMessageException | UnknownRoutingKeyException $e) {
            $this->logger->warning(
                $e->getMessage(),
                $this->buildLogContext($routingKey, $messageId, $eventId, [
                    'exception' => $e,
                ]),
            );

            return InboundOutcome::Reject;
        } catch (InvalidReservationTransitionException $e) {
            $this->logger->warning(
                $e->getMessage(),
                $this->buildLogContext($routingKey, $messageId, $eventId, [
                    'exception' => $e,
                ]),
            );

            return InboundOutcome::Reject;
        } catch (RetryableMessageException $e) {
            $this->logger->warning(
                $e->getMessage(),
                $this->buildLogContext($routingKey, $messageId, $eventId, [
                    'exception' => $e,
                ]),
            );

            return InboundOutcome::Retry;
        } catch (Throwable $e) {
            $this->logger->error(
                $e->getMessage(),
                $this->buildLogContext($routingKey, $messageId, $eventId, [
                    'exception' => $e,
                ]),
            );

            return InboundOutcome::Retry;
        }
    }

    /**
     * @param list<string> $productIds
     */
    private function invalidateProductCards(
        array $productIds,
        string $routingKey,
        ?string $messageId,
        ?string $eventId,
    ): void {
        foreach ($productIds as $productId) {
            try {
                $this->productCache->invalidateCard($productId);
            } catch (Throwable $e) {
                $this->logger->warning(
                    'Failed to invalidate product card cache after inbound message.',
                    $this->buildLogContext($routingKey, $messageId, $eventId, [
                        'product_id' => $productId,
                        'exception' => $e,
                    ]),
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function buildLogContext(
        string $routingKey,
        ?string $messageId,
        ?string $eventId,
        array $extra = [],
    ): array {
        $context = ['routing_key' => $routingKey];

        if ($messageId !== null) {
            $context['message_id'] = $messageId;
        }

        if ($eventId !== null) {
            $context['event_id'] = $eventId;
        }

        return array_merge($context, $extra);
    }
}
