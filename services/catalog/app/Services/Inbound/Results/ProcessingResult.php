<?php

declare(strict_types=1);

namespace App\Services\Inbound\Results;

final readonly class ProcessingResult
{
    /**
     * @param list<string> $affectedProductIds Products whose stock counters actually changed.
     */
    public function __construct(
        public ProcessingStatus $status,
        public ?StockRejectionReason $reason = null,
        public array $affectedProductIds = [],
    ) {
    }

    /**
     * @param list<string> $affectedProductIds
     */
    public static function applied(array $affectedProductIds = []): self
    {
        return new self(ProcessingStatus::Applied, affectedProductIds: $affectedProductIds);
    }

    public static function duplicate(): self
    {
        return new self(ProcessingStatus::Duplicate);
    }

    public static function rejected(StockRejectionReason $reason): self
    {
        return new self(ProcessingStatus::Rejected, $reason);
    }
}
