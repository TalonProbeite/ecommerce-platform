<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Saga\ExpireUnpaidOrders\ExpireUnpaidOrders;
use Illuminate\Console\Command;

final class ExpireUnpaidOrdersCommand extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Cancel unpaid orders older than 30 minutes';

    public function handle(ExpireUnpaidOrders $expireUnpaidOrders): int
    {
        $cancelled = $expireUnpaidOrders->execute();

        $this->info(sprintf('Cancelled unpaid orders: %d', $cancelled));

        return self::SUCCESS;
    }
}
