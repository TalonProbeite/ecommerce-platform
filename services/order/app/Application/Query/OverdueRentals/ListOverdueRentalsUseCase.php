<?php

declare(strict_types=1);

namespace App\Application\Query\OverdueRentals;

use App\Application\Exceptions\ForbiddenException;
use App\Application\Exceptions\InvalidCursor;
use App\Application\Ports\AuthenticatedUser;
use App\Application\Ports\Clock;
use App\Application\Ports\OverdueRentalReadRepository;
use App\Policies\OrderPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ListOverdueRentalsUseCase
{
    public const int MIN_LIMIT = 1;

    public const int MAX_LIMIT = 100;

    public const int DEFAULT_LIMIT = 20;

    private const int MICROSECONDS_PER_HOUR = 3_600_000_000;

    public function __construct(
        private OverdueRentalReadRepository $overdueRentals,
        private Clock $clock,
        private OrderPolicy $policy,
    ) {
    }

    public function execute(
        AuthenticatedUser $user,
        ?string $storeId,
        ?string $cursor,
        int $limit,
    ): OverdueRentalPage {
        if (! $this->policy->viewOverdue($user)) {
            throw new ForbiddenException('Only admin and analyst can view overdue rentals.');
        }

        if ($limit < self::MIN_LIMIT || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException('The limit must be between 1 and 100.');
        }

        $cursor = $cursor === '' ? null : $cursor;

        if ($cursor !== null && OverdueRentalCursor::decode($cursor) === null) {
            throw new InvalidCursor();
        }

        $now = $this->clock->now();

        $page = $this->overdueRentals->find(
            new OverdueRentalCriteria($storeId, $now),
            $cursor,
            $limit,
        );

        $items = [];

        foreach ($page->items as $item) {
            $items[] = $item->withOverdueHours($this->overdueHours($item, $now));
        }

        return new OverdueRentalPage($items, $page->nextCursor);
    }

    private function overdueHours(OverdueRentalItem $item, CarbonImmutable $now): int
    {
        $overdueMicroseconds = (int) $item->rentalEndAt->diffInMicroseconds($now, false);

        if ($overdueMicroseconds <= 0) {
            return 0;
        }

        return (int) ceil($overdueMicroseconds / self::MICROSECONDS_PER_HOUR);
    }
}
