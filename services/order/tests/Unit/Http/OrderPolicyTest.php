<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Enums\Role;
use App\Http\Auth\AuthUser;
use App\Policies\OrderPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Domain\OrderFixtures;

final class OrderPolicyTest extends TestCase
{
    use OrderFixtures;

    /**
     * @return array<string, array{0: Role, 1: string, 2: ?string, 3: bool}>
     */
    public static function viewMatrix(): array
    {
        return [
            'customer owner' => [Role::CUSTOMER, self::CUSTOMER_ID, null, true],
            'customer foreign' => [Role::CUSTOMER, '11111111-1111-4111-8111-999999999999', null, false],
            'manager same store' => [Role::STORE_MANAGER, '11111111-1111-4111-8111-999999999999', self::STORE_ID, true],
            'manager other store' => [Role::STORE_MANAGER, '11111111-1111-4111-8111-999999999999', '22222222-2222-4222-8222-999999999999', false],
            'manager without store' => [Role::STORE_MANAGER, '11111111-1111-4111-8111-999999999999', null, false],
            'admin' => [Role::ADMIN, '11111111-1111-4111-8111-999999999999', null, true],
            'analyst' => [Role::ANALYST, '11111111-1111-4111-8111-999999999999', null, true],
        ];
    }

    /**
     * @return array<string, array{0: Role, 1: bool}>
     */
    public static function createMatrix(): array
    {
        return [
            'customer' => [Role::CUSTOMER, true],
            'store_manager' => [Role::STORE_MANAGER, false],
            'admin' => [Role::ADMIN, false],
            'analyst' => [Role::ANALYST, false],
        ];
    }

    #[Test]
    #[DataProvider('viewMatrix')]
    public function it_applies_the_view_matrix(
        Role $role,
        string $userId,
        ?string $storeId,
        bool $expected,
    ): void {
        $policy = new OrderPolicy();
        $order = $this->makeOrder();

        self::assertSame(
            $expected,
            $policy->view(new AuthUser($userId, $role, $storeId), $order),
        );
    }

    #[Test]
    #[DataProvider('createMatrix')]
    public function it_applies_the_create_matrix(Role $role, bool $expected): void
    {
        $policy = new OrderPolicy();

        self::assertSame($expected, $policy->create(new AuthUser(self::CUSTOMER_ID, $role)));
    }
}
