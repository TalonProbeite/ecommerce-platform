<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Application\Ports\Clock;
use App\Application\Ports\PaymentGateway;
use App\Infrastructure\Persistence\Models\OrderModel;
use App\Infrastructure\Persistence\Models\PaymentModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\PostgresTestCase;
use Tests\Support\Fakes\FakePaymentGateway;
use Tests\Support\Fakes\FixedClock;

final class StripeWebhookPostgresTest extends PostgresTestCase
{
    private const string WEBHOOK_SECRET = 'whsec_test_secret';
    private const string CUSTOMER_ID = '11111111-1111-4111-8111-111111111111';
    private const string STORE_ID = '22222222-2222-4222-8222-222222222222';

    private CarbonImmutable $now;
    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-11-20T12:00:00.123456+00:00');
        config(['stripe.webhook_secret' => self::WEBHOOK_SECRET]);
        $this->app->instance(Clock::class, new FixedClock($this->now));

        $this->gateway = new FakePaymentGateway();
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    #[Test]
    public function it_marks_the_order_paid_and_deduplicates_repeated_delivery(): void
    {
        $order = $this->order('payment_pending');
        $this->payment($order, 'pi_test_1');
        $payload = $this->payload('evt_test_1', 'pi_test_1');

        $this->postWebhook($payload)->assertStatus(200);

        self::assertSame('paid', $this->orderStatus($order));
        self::assertSame(1, DB::table('outbox')->where('routing_key', 'order.paid')->count());

        $this->postWebhook($payload)->assertStatus(200);

        self::assertSame(1, DB::table('outbox')->where('routing_key', 'order.paid')->count());
        self::assertSame(1, DB::table('stripe_events')->where('id', 'evt_test_1')->count());
        self::assertSame('succeeded', PaymentModel::query()->where('order_id', $order->id)->value('status'));
    }

    #[Test]
    public function it_retries_when_the_payment_is_not_committed_yet(): void
    {
        $order = $this->order('payment_pending');
        $payload = $this->payload('evt_before_commit', 'pi_late');

        $this->postWebhook($payload)->assertStatus(500);

        self::assertSame(0, DB::table('stripe_events')->where('id', 'evt_before_commit')->count());
        self::assertSame('payment_pending', $this->orderStatus($order));

        $this->payment($order, 'pi_late');

        $this->postWebhook($payload)->assertStatus(200);

        self::assertSame('paid', $this->orderStatus($order));
        self::assertSame(1, DB::table('outbox')->where('routing_key', 'order.paid')->count());
    }

    #[Test]
    public function it_refunds_a_payment_that_succeeds_after_cancellation(): void
    {
        $order = $this->order('cancelled');
        $payment = $this->payment($order, 'pi_cancelled');

        $this->postWebhook($this->payload('evt_cancelled', 'pi_cancelled'))->assertStatus(200);

        self::assertSame('cancelled', $this->orderStatus($order));
        self::assertSame(0, DB::table('outbox')->where('routing_key', 'order.paid')->count());

        $refund = DB::table('refunds')->where('payment_id', $payment->id)->first();

        self::assertNotNull($refund);
        self::assertSame('order_cancelled', $refund->reason);
        self::assertSame('succeeded', $refund->status);
        self::assertSame('re_test_1', $refund->provider_refund_id);
        self::assertSame(7000, (int) $refund->amount);
        self::assertCount(1, $this->gateway->refundCalls);
        self::assertSame('order:' . $order->id . ':refund:all', $this->gateway->refundCalls[0]['idempotencyKey']);
    }

    private function order(string $status): OrderModel
    {
        $order = $this->newOrder([
            'customer_id' => self::CUSTOMER_ID,
            'store_id' => self::STORE_ID,
            'status' => $status,
            'subtotal_amount' => 2000,
            'deposit_amount' => 5000,
        ]);

        $this->newOrderItem($order);

        return $order;
    }

    private function payment(OrderModel $order, string $providerPaymentId): PaymentModel
    {
        return PaymentModel::create([
            'id' => (string) Str::uuid7(),
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_payment_id' => $providerPaymentId,
            'amount' => 7000,
            'currency' => 'EUR',
            'status' => 'processing',
            'failure_code' => null,
            'succeeded_at' => null,
            'created_at' => $this->now->format('Y-m-d H:i:s.uP'),
            'updated_at' => $this->now->format('Y-m-d H:i:s.uP'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $eventId, string $paymentId): array
    {
        return [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $paymentId,
                    'object' => 'payment_intent',
                    'amount_received' => 7000,
                    'currency' => 'eur',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $content = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $content, self::WEBHOOK_SECRET);

        return $this->call(
            'POST',
            '/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => sprintf('t=%d,v1=%s', $timestamp, $signature),
            ],
            $content,
        );
    }

    private function orderStatus(OrderModel $order): string
    {
        return (string) DB::table('orders')->where('id', $order->id)->value('status');
    }
}
