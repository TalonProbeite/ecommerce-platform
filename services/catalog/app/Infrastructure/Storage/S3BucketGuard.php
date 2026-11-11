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

    public function __construct(
        private readonly S3Client $client,
    ) {
    }

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

        $bucket = (string) config("filesystems.disks.{$disk}.bucket");

        if (! $this->client->doesBucketExist($bucket)) {
            try {
                $this->client->createBucket(['Bucket' => $bucket]);
            } catch (AwsException $e) {
                if ($e->getAwsErrorCode() !== self::ALREADY_OWNED_CODE) {
                    throw $e;
                }
            }
        }

        $this->verifiedDisks[$disk] = true;
    }
}
