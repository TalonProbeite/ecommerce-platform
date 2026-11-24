<?php

declare(strict_types=1);

namespace App\Contracts\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;

interface AmqpConnectionFactoryInterface
{
    public function connect(): AMQPStreamConnection;
}
