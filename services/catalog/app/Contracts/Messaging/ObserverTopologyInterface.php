<?php

declare(strict_types=1);

namespace App\Contracts\Messaging;

interface ObserverTopologyInterface
{
    public function queueName(): string;

    public function exchangeName(): string;

    public function declare(): void;
}
