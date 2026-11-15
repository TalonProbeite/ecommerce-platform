<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\IdGenerator;

final class SequenceIdGenerator implements IdGenerator
{
    private int $next = 1;

    public function next(): string
    {
        $sequence = $this->next++;

        return sprintf('01930000-0000-7000-8000-%012d', $sequence);
    }
}
