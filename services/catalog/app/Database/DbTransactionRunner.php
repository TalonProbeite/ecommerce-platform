<?php

declare(strict_types=1);

namespace App\Database;

use App\Contracts\Database\TransactionRunnerInterface;
use Closure;
use Illuminate\Support\Facades\DB;

final class DbTransactionRunner implements TransactionRunnerInterface
{
    /**
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public function run(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
