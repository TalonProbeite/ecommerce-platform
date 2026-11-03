<?php

declare(strict_types=1);

namespace App\Contracts\Messaging;

use App\Domain\Contracts\EventInterface;

interface EventPublisherInterface
{
    /**
     * Publish an event.
     */
    public function publish(EventInterface $event): void;
}
