<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Psr\Log\AbstractLogger;
use Stringable;

final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string|Stringable, context: array<string, mixed>}>
     */
    private array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level: string, message: string|Stringable, context: array<string, mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }

    public function hasLevel(string $level): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level) {
                return true;
            }
        }

        return false;
    }
}
