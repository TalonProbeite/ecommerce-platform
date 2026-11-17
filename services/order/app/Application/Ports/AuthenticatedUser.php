<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface AuthenticatedUser
{
    public function id(): string;
}
