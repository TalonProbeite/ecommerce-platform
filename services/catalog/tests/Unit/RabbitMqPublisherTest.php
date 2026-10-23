<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Messaging\Events\ProductCreated;
use App\Messaging\RabbitMqPublisher;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

class RabbitMqPublisherTest extends TestCase
{
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $container = new Container();
        $container->instance('config', new Repository(['rabbitmq' => [
            'exchange' => ['name' => 'test.catalog.events'], 'heartbeat' => 100000,
            'confirm_timeout' => 0.25,
        ]]));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function publisher(AMQPStreamConnection $connection, AMQPChannel $channel): RabbitMqPublisher
    {
        // Seed an already connected transport: constructors of both mocks are disabled.
        $publisher = new RabbitMqPublisher();
        foreach (['connection' => $connection, 'channel' => $channel, 'lastActivityAt' => hrtime(true) / 1e9] as $property => $value) {
            (new ReflectionProperty($publisher, $property))->setValue($publisher, $value);
        }
        return $publisher;
    }

    public function test_publish_uses_json_persistent_message_and_reuses_live_transport(): void
    {
        $connection = $this->createMock(AMQPStreamConnection::class);
        $channel = $this->createMock(AMQPChannel::class);
        $connection->expects(self::exactly(2))->method('isConnected')->willReturn(true);
        $connection->expects(self::never())->method('channel');
        $channel->expects(self::exactly(2))->method('is_open')->willReturn(true);
        $channel->expects(self::never())->method('exchange_declare');
        $event = new ProductCreated('product', 'store', 'Дрель', 12345);
        $channel->expects(self::exactly(2))->method('basic_publish')->with(
            self::callback(function (AMQPMessage $message) use ($event): bool {
                self::assertSame($event->toArray(), json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR));
                self::assertSame('application/json', $message->get('content_type'));
                self::assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $message->get('delivery_mode'));
                return true;
            }),
            'test.catalog.events',
            'product.created',
            true,
        );
        $channel->expects(self::exactly(2))->method('wait_for_pending_acks_returns')->with(0.25);
        $publisher = $this->publisher($connection, $channel);
        $publisher->publish($event);
        $publisher->publish($event);
        $publisher->close();
    }

    public function test_invalid_json_fails_before_touching_transport(): void
    {
        $connection = $this->createMock(AMQPStreamConnection::class);
        $channel = $this->createMock(AMQPChannel::class);
        $connection->expects(self::never())->method('isConnected');
        $channel->expects(self::never())->method('basic_publish');
        $event = $this->createMock(\App\Contracts\Messaging\EventInterface::class);
        $event->method('toArray')->willReturn(['invalid' => "\xB1\x31"]);
        $event->expects(self::never())->method('routingKey');
        $publisher = $this->publisher($connection, $channel);
        $this->expectException(\JsonException::class);
        $publisher->publish($event);
    }

    public static function failureStages(): array
    {
        return ['publish' => ['basic_publish'], 'ambiguous confirmation' => ['wait_for_pending_acks_returns']];
    }

    #[DataProvider('failureStages')]
    public function test_transport_error_is_rethrown_without_republishing(string $stage): void
    {
        $connection = $this->createMock(AMQPStreamConnection::class);
        $channel = $this->createMock(AMQPChannel::class);
        $connection->method('isConnected')->willReturn(true);
        $channel->method('is_open')->willReturn(true);
        $error = new RuntimeException('Transport failure');
        $publish = $channel->expects(self::once())->method('basic_publish');
        $confirm = $channel->expects($stage === 'basic_publish' ? self::never() : self::once())->method('wait_for_pending_acks_returns');
        ($stage === 'basic_publish' ? $publish : $confirm)->willThrowException($error);
        // Cleanup errors must not hide the original ambiguous failure.
        $channel->expects(self::once())->method('close')->willThrowException(new RuntimeException('Close failed'));
        $connection->expects(self::once())->method('close')->willThrowException(new RuntimeException('Close failed'));
        $publisher = $this->publisher($connection, $channel);
        try {
            $publisher->publish(new ProductCreated('product', 'store', 'Drill', 100));
            self::fail('Expected the original transport error.');
        } catch (RuntimeException $actual) {
            self::assertSame($error, $actual);
        }
        $publisher->close();
    }
}
