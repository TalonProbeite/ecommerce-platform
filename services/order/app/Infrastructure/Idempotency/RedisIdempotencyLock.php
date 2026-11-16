<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use App\Application\Ports\IdempotencyLock;
use Illuminate\Redis\Connections\Connection;

final readonly class RedisIdempotencyLock implements IdempotencyLock
{
    private const string RELEASE_SCRIPT = <<<'LUA'
if redis.call('get', KEYS[1]) == ARGV[1] then
    return redis.call('del', KEYS[1])
end

return 0
LUA;

    public function __construct(private Connection $connection)
    {
    }

    public function acquire(string $key, string $token, int $ttlSeconds): bool
    {
        $result = $this->connection->command('set', [$key, $token, ['NX', 'EX' => $ttlSeconds]]);

        return $result === true
            || $result === 1
            || $result === 'OK'
            || (is_object($result) && (string) $result === 'OK');
    }

    public function release(string $key, string $token): void
    {
        $this->connection->eval(self::RELEASE_SCRIPT, 1, $key, $token);
    }
}
