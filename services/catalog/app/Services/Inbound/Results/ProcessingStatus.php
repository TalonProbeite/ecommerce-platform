<?php

declare(strict_types=1);

namespace App\Services\Inbound\Results;

enum ProcessingStatus: string
{
    case Applied = 'applied';
    case Duplicate = 'duplicate';
    case Rejected = 'rejected';
}
