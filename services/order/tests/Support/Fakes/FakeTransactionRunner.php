<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\TransactionRunnerInterface;
use Closure;

final class FakeTransactionRunner implements TransactionRunnerInterface
{
    public bool $wasCalled = false;

    /**
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public function run(Closure $callback): mixed
    {
        $this->wasCalled = true;

        return $callback();
    }
}
