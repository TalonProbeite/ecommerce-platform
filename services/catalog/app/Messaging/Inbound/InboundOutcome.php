<?php

declare(strict_types=1);

namespace App\Messaging\Inbound;

enum InboundOutcome: string
{
    case Ack = 'ack';
    case Reject = 'reject';
    case Retry = 'retry';
}
