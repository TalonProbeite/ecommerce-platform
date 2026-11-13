<?php

declare(strict_types=1);

namespace App\Messaging\Inbound;

use App\Messaging\Inbound\Exceptions\UnknownRoutingKeyException;
use InvalidArgumentException;
use LogicException;

final class InboundHandlerRegistry
{
    /**
     * @var array<string, InboundHandler>
     */
    private array $handlers = [];

    /**
     * @param iterable<mixed> $handlers
     */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $key => $handler) {
            if (! $handler instanceof InboundHandler) {
                throw new InvalidArgumentException(sprintf(
                    'Handler must implement %s, %s given.',
                    InboundHandler::class,
                    get_debug_type($handler),
                ));
            }

            $routingKey = is_string($key) && $key !== '' ? $key : $handler->routingKey();

            if (isset($this->handlers[$routingKey])) {
                throw new LogicException(sprintf(
                    'Duplicate handler registered for routing key "%s".',
                    $routingKey,
                ));
            }

            $this->handlers[$routingKey] = $handler;
        }
    }

    /**
     * @throws UnknownRoutingKeyException
     */
    public function get(string $routingKey): InboundHandler
    {
        return $this->handlers[$routingKey] ?? throw new UnknownRoutingKeyException($routingKey);
    }

    public function has(string $routingKey): bool
    {
        return isset($this->handlers[$routingKey]);
    }
}
