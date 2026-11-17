<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class MessagingContract
{
    public static function path(): string
    {
        $configured = getenv('MESSAGING_CONTRACT_PATH');

        $candidates = [
            is_string($configured) && $configured !== '' ? $configured : null,
            dirname(__DIR__, 4) . '/docs/contracts/messaging.md',
            '/docs/contracts/messaging.md',
            dirname(__DIR__, 3) . '/docs/contracts/messaging.md',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Messaging contract not found. Mount the repository docs or set MESSAGING_CONTRACT_PATH.',
        );
    }

    private function __construct()
    {
    }
}
