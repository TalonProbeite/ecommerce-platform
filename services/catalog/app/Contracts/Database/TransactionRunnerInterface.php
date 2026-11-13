<?php

declare(strict_types=1);

namespace App\Contracts\Database;

use Closure;

interface TransactionRunnerInterface
{
    /**
     * Run the callback inside a database transaction.
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public function run(Closure $callback): mixed;
}
