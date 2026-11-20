<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Order\ActorRole;
use App\Enums\Role;
use App\Infrastructure\Persistence\SchemaValues;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActorRoleTest extends TestCase
{
    #[Test]
    public function schema_values_match_the_actor_role_enum(): void
    {
        self::assertSame($this->actorRoleValues(), $this->sorted(SchemaValues::ACTOR_ROLE));
    }

    #[Test]
    public function migration_checks_match_the_actor_role_enum(): void
    {
        $base = dirname(__DIR__, 3) . '/database/migrations/';

        $events = (string) file_get_contents($base . '2026_12_01_100005_create_order_events_table.php');
        $comments = (string) file_get_contents($base . '2026_12_01_100006_create_order_comments_table.php');

        self::assertSame($this->actorRoleValues(), $this->checkValues($events, 'actor_role'));
        self::assertSame($this->actorRoleValues(), $this->checkValues($comments, 'author_role'));
    }

    #[Test]
    public function every_jwt_role_maps_to_an_actor_role(): void
    {
        foreach (Role::cases() as $role) {
            self::assertSame($role->value, ActorRole::fromRole($role)->value);
        }
    }

    #[Test]
    public function actor_role_contains_system_and_stripe(): void
    {
        self::assertNotNull(ActorRole::tryFrom('system'));
        self::assertNotNull(ActorRole::tryFrom('stripe'));
    }

    /**
     * @return list<string>
     */
    private function actorRoleValues(): array
    {
        return $this->sorted(array_map(
            static fn (ActorRole $role): string => $role->value,
            ActorRole::cases(),
        ));
    }

    /**
     * @return list<string>
     */
    private function checkValues(string $contents, string $column): array
    {
        self::assertSame(
            1,
            preg_match(sprintf('/%s IN \\(([^)]+)\\)/s', $column), $contents, $matches),
        );

        preg_match_all("/'([^']+)'/", $matches[1], $quoted);

        return $this->sorted($quoted[1]);
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return array_values($values);
    }
}
