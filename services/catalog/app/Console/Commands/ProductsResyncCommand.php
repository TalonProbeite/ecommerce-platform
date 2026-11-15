<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Outbox\OutboxWriterInterface;
use App\Messaging\Events\ProductCreated;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ProductsResyncCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'catalog:products:resync {--chunk=500}';

    /**
     * @var string
     */
    protected $description = 'Queue product.created events for every non-deleted product';

    public function handle(OutboxWriterInterface $outbox): int
    {
        $chunkSize = $this->chunkSize();
        $queued = 0;

        Product::query()
            ->whereNull('deleted_at')
            ->chunkById($chunkSize, function ($products) use ($outbox, &$queued): void {
                DB::transaction(function () use ($products, $outbox, &$queued): void {
                    foreach ($products as $product) {
                        $occurredAt = CarbonImmutable::instance($product->updated_at);
                        $outbox->enqueue(ProductCreated::fromModel($product), $occurredAt);
                        $queued++;
                    }
                });
            }, 'id');

        $this->info(sprintf('Queued %d product.created events.', $queued));

        return self::SUCCESS;
    }

    private function chunkSize(): int
    {
        $option = $this->option('chunk');
        $chunkSize = is_numeric($option) ? (int) $option : 500;

        return $chunkSize > 0 ? $chunkSize : 500;
    }
}
