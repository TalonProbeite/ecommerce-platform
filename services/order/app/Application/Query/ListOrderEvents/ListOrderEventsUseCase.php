<?php

declare(strict_types=1);

namespace App\Application\Query\ListOrderEvents;

use App\Application\Exceptions\ForbiddenException;
use App\Application\Exceptions\InvalidCursor;
use App\Application\Exceptions\OrderNotFound;
use App\Application\Ports\AuthenticatedUser;
use App\Application\Ports\OrderEventReadRepository;
use App\Policies\OrderPolicy;
use InvalidArgumentException;

final readonly class ListOrderEventsUseCase
{
    public const int MIN_LIMIT = 1;

    public const int MAX_LIMIT = 100;

    public const int DEFAULT_LIMIT = ListOrderEventsQuery::DEFAULT_LIMIT;

    public function __construct(
        private OrderEventReadRepository $events,
        private OrderPolicy $policy,
    ) {
    }

    public function execute(
        AuthenticatedUser $user,
        string $orderId,
        ?string $cursor,
        int $limit,
    ): OrderEventPage {
        if (! $this->policy->viewEvents($user)) {
            throw new ForbiddenException('Only admin and analyst can view order events.');
        }

        if ($limit < self::MIN_LIMIT || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException('The limit must be between 1 and 100.');
        }

        $decodedCursor = null;

        if ($cursor !== null && $cursor !== '') {
            $decodedCursor = OrderEventCursor::decode($cursor);

            if ($decodedCursor === null) {
                throw new InvalidCursor();
            }
        }

        if (! $this->events->orderExists($orderId)) {
            throw new OrderNotFound($orderId);
        }

        return $this->events->list(new ListOrderEventsCriteria(
            orderId: $orderId,
            cursor: $decodedCursor,
            limit: $limit,
        ));
    }
}
