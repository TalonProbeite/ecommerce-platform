<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Enums\Role;

interface AuthenticatedUser
{
    public function id(): string;

    public function role(): Role;

    public function storeId(): ?string;
}
