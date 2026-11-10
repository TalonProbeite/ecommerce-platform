<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use Aws\S3\S3Client;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;

final class ReadinessChecker
{
    private const PROBE_TIMEOUT_SECONDS = 2.0;

    /**
     * Run all dependency probes.
     *
     * Each entry contains: ok, critical, latency_ms.
     *
     * @return array<string, array{ok: bool, critical: bool, latency_ms: float}>
     */
    public function check(): array
    {
        return [
            'database' => $this->probe(true, fn () => DB::select('select 1')),
            'redis' => $this->probe(false, fn () => Redis::connection('cache')->ping()),
            'storage' => $this->probe(false, fn () => $this->checkStorage()),
            'rabbitmq' => $this->probe(false, fn () => $this->checkRabbitMq()),
        ];
    }

    /**
     * Whether readiness passes: all critical probes must be ok.
     *
     * @param array<string, array{ok: bool, critical: bool, latency_ms: float}> $results
     */
    public function isReady(array $results): bool
    {
        foreach ($results as $result) {
            if ($result['critical'] && ! $result['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Health probes must turn dependency failures into a status value,
     * otherwise a single failing dependency would fail the whole endpoint.
     * Error details go to the log, not to the response.
     *
     * @return array{ok: bool, critical: bool, latency_ms: float}
     */
    private function probe(bool $critical, Closure $probe): array
    {
        $started = hrtime(true);

        try {
            $probe();
            $ok = true;
        } catch (Throwable $e) {
            Log::warning('Readiness probe failed', ['exception' => $e->getMessage()]);
            $ok = false;
        }

        return [
            'ok' => $ok,
            'critical' => $critical,
            'latency_ms' => round((hrtime(true) - $started) / 1e6, 1),
        ];
    }

    private function checkStorage(): void
    {
        $config = config('filesystems.disks.seaweedfs');

        $client = new S3Client([
            'version' => 'latest',
            'region' => (string) $config['region'],
            'endpoint' => (string) $config['endpoint'],
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => (string) $config['key'],
                'secret' => (string) $config['secret'],
            ],
            'http' => [
                'connect_timeout' => self::PROBE_TIMEOUT_SECONDS,
                'timeout' => self::PROBE_TIMEOUT_SECONDS,
            ],
        ]);

        $client->headBucket(['Bucket' => (string) $config['bucket']]);
    }

    private function checkRabbitMq(): void
    {
        $connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
            config('rabbitmq.vhost'),
            connection_timeout: self::PROBE_TIMEOUT_SECONDS,
            read_write_timeout: self::PROBE_TIMEOUT_SECONDS,
        );

        $connection->close();
    }
}
