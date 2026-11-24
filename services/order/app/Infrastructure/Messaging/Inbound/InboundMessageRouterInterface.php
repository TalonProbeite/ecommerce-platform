<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound;

use PhpAmqpLib\Message\AMQPMessage;

interface InboundMessageRouterInterface
{
    public function dispatch(AMQPMessage $message): InboundOutcome;
}
