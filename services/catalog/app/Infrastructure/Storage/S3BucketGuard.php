<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;

final class S3BucketGuard
{
    private const ALREADY_OWNED_CODE = 'BucketAlreadyOwnedByYou';

    /** @var array<string, true> Disks whose bucket is already verified in this worker. */
    private array $verifiedDisks = [];

    /**
     * Create the disk's bucket if it does not exist yet.
     * Runs at most once per disk per worker process.
     *
     * @throws AwsException
     */
    public function ensure(string $disk): void
    {
        if (isset($this->verifiedDisks[$disk])) {
            return;
        }

        $config = config("filesystems.disks.{$disk}");
        $bucket = (string) $config['bucket'];

        $client = new S3Client([
            'version' => 'latest',
            'region' => (string) $config['region'],
            'endpoint' => (string) $config['endpoint'],
            'use_path_style_endpoint' => (bool) $config['use_path_style_endpoint'],
            'credentials' => [
                'key' => (string) $config['key'],
                'secret' => (string) $config['secret'],
            ],
        ]);

        if (! $client->doesBucketExist($bucket)) {
            try {
                $client->createBucket(['Bucket' => $bucket]);
            } catch (AwsException $e) {
                if ($e->getAwsErrorCode() !== self::ALREADY_OWNED_CODE) {
                    throw $e;
                }
            }
        }

        $this->verifiedDisks[$disk] = true;
    }
}
