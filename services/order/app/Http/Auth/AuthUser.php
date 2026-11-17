<?php

declare(strict_types=1);

namespace App\Http\Auth;

use App\Application\Ports\AuthenticatedUser;
use App\Enums\Role;

final readonly class AuthUser implements AuthenticatedUser
{
    public const string ATTRIBUTE = 'auth_user';

    public function __construct(
        public string $id,
        public Role $role,
        public ?string $storeId = null,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }
}
