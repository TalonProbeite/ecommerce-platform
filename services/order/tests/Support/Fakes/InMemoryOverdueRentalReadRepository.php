<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\OverdueRentalReadRepository;
use App\Application\Query\OverdueRentals\OverdueRentalCriteria;
use App\Application\Query\OverdueRentals\OverdueRentalPage;

final class InMemoryOverdueRentalReadRepository implements OverdueRentalReadRepository
{
    public ?OverdueRentalCriteria $criteria = null;

    public ?string $cursor = null;

    public ?int $limit = null;

    public function __construct(private OverdueRentalPage $page = new OverdueRentalPage([], null))
    {
    }

    public function setPage(OverdueRentalPage $page): void
    {
        $this->page = $page;
    }

    public function find(OverdueRentalCriteria $criteria, ?string $cursor, int $limit): OverdueRentalPage
    {
        $this->criteria = $criteria;
        $this->cursor = $cursor;
        $this->limit = $limit;

        return $this->page;
    }
}
