<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Outbox;

use App\Contracts\Outbox\OutboxMessagePublisherInterface;
use App\Contracts\Outbox\OutboxRepositoryInterface;
use App\Outbox\OutboxRelay;
use Mockery;
use Psr\Log\NullLogger;
use Tests\PostgresTestCase;

final class MessagingRelayCommandTest extends PostgresTestCase
{
    public function test_relay_command_stops_after_max_batches(): void
    {
        $fakeRepo = Mockery::mock(OutboxRepositoryInterface::class);
        $fakeRepo->shouldReceive('lockPendingBatch')
            ->times(3)
            ->andReturn([]);

        $fakePublisher = Mockery::mock(OutboxMessagePublisherInterface::class);

        $relay = new OutboxRelay($fakeRepo, $fakePublisher, new NullLogger());
        $this->app->instance(OutboxRelay::class, $relay);

        $this->artisan('messaging:relay', ['--max-batches' => 3])
            ->assertSuccessful();
    }
}
