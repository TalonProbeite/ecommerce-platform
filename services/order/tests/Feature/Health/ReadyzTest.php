<?php

declare(strict_types=1);

namespace Tests\Feature\Health;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReadyzTest extends TestCase
{
    #[Test]
    public function it_is_not_ready_when_the_jwt_public_key_is_missing(): void
    {
        config(['jwt.public_key_b64' => '']);

        $this->get('/readyz')
            ->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.jwt.ok', false)
            ->assertJsonPath('checks.jwt.critical', true);
    }
}
