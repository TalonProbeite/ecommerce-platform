<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface IdGenerator
{
    public function next(): string;
}
