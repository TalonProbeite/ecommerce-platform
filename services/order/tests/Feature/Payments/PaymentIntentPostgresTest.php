<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Application\Ports\PaymentGateway;
use App\Infrastructure\Persistence\Models\OrderModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\PostgresTestCase;
use Tests\Support\Fakes\FakePaymentGateway;

final class PaymentIntentPostgresTest extends PostgresTestCase
{
    private const string CUSTOMER_ID = '11111111-1111-4111-8111-111111111111';
    private const string OTHER_CUSTOMER_ID = '11111111-1111-4111-8111-222222222222';
    private const string STORE_ID = '22222222-2222-4222-8222-222222222222';

    private FakePaymentGateway $gateway;
    private string $privateKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        self::assertNotFalse($privateKey);

        $details = openssl_pkey_get_details($privateKey);

        self::assertIsArray($details);
        self::assertIsString($details['key'] ?? null);

        $privateKeyPem = '';
        self::assertTrue(openssl_pkey_export($privateKey, $privateKeyPem));

        $this->privateKeyPem = $privateKeyPem;
        config(['jwt.public_key_b64' => base64_encode($details['key']), 'jwt.algo' => 'RS256']);

        $this->gateway = new FakePaymentGateway();
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    #[Test]
    public function it_creates_a_payment_intent_and_reuses_it_on_repeat(): void
    {
        $order = $this->stockReservedOrder();

        $first = $this->postJson(
            '/api/v1/orders/' . $order->id . '/payment-intent',
            [],
            $this->headers($this->token(self::CUSTOMER_ID)),
        );

        $first->assertStatus(201)
            ->assertJson([
                'client_secret' => 'cs_test_1',
                'status' => 'requires_payment_method',
                'amount_total' => 7000,
                'currency' => 'EUR',
            ]);

        self::assertCount(1, $this->gateway->createCalls);
        self::assertSame(7000, $this->gateway->createCalls[0]['request']->amount);
        self::assertSame('order:' . $order->id . ':pi:1', $this->gateway->createCalls[0]['idempotencyKey']);

        $second = $this->postJson(
            '/api/v1/orders/' . $order->id . '/payment-intent',
            [],
            $this->headers($this->token(self::CUSTOMER_ID)),
        );

        $second->assertStatus(200)
            ->assertJsonPath('client_secret', 'cs_test_1')
            ->assertJsonPath('amount_total', 7000);

        self::assertCount(1, $this->gateway->createCalls);
        self::assertSame(['pi_test_1'], $this->gateway->retrieveCalls);
    }

    #[Test]
    public function it_returns_404_for_another_customer(): void
    {
        $order = $this->stockReservedOrder();

        $this->postJson(
            '/api/v1/orders/' . $order->id . '/payment-intent',
            [],
            $this->headers($this->token(self::OTHER_CUSTOMER_ID)),
        )->assertStatus(404);

        self::assertCount(0, $this->gateway->createCalls);
    }

    private function stockReservedOrder(): OrderModel
    {
        $order = $this->newOrder([
            'customer_id' => self::CUSTOMER_ID,
            'store_id' => self::STORE_ID,
            'status' => 'stock_reserved',
            'subtotal_amount' => 2000,
            'deposit_amount' => 5000,
        ]);

        $this->newOrderItem($order);

        return $order;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $token): array
    {
        $this->withCredentials();
        $this->withUnencryptedCookie('access_token', $token);

        return ['Accept' => 'application/json'];
    }

    private function token(string $userId): string
    {
        $now = time();
        $header = $this->encodeJson(['alg' => 'RS256', 'typ' => 'JWT']);
        $body = $this->encodeJson([
            'iat' => $now,
            'exp' => $now + 3600,
            'user_id' => $userId,
            'role' => 'customer',
        ]);
        $signature = '';

        openssl_sign($header . '.' . $body, $signature, $this->privateKeyPem, OPENSSL_ALGO_SHA256);

        return $header . '.' . $body . '.' . $this->encode($signature);
    }

    private function encodeJson(mixed $value): string
    {
        return $this->encode(json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
