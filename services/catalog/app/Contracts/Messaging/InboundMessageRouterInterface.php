<?php

declare(strict_types=1);

namespace App\Contracts\Messaging;

use App\Messaging\Inbound\InboundOutcome;
use PhpAmqpLib\Message\AMQPMessage;

interface InboundMessageRouterInterface
{
    public function dispatch(AMQPMessage $message): InboundOutcome;
}
