<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait UsesPreciseDates
{
    protected function initializeUsesPreciseDates(): void
    {
        $this->dateFormat = 'Y-m-d H:i:s.uP';
    }
}
