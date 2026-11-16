<?php

declare(strict_types=1);

namespace App\Http\Auth;

use App\Enums\Role;

final readonly class AuthUser
{
    public const string ATTRIBUTE = 'auth_user';

    public function __construct(
        public string $id,
        public Role $role,
        public ?string $storeId = null,
    ) {
    }
}
