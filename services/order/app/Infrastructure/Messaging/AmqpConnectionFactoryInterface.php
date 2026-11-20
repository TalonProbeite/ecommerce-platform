<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;

interface AmqpConnectionFactoryInterface
{
    public function connect(): AMQPStreamConnection;
}
