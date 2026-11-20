<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Payments;

use App\Application\Exceptions\InvalidWebhookSignature;
use App\Infrastructure\Payments\Stripe\StripeWebhookSignatureVerifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StripeWebhookSignatureVerifierTest extends TestCase
{
    private const string SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['stripe.webhook_secret' => self::SECRET]);
    }

    #[Test]
    public function it_accepts_a_valid_stripe_signature_without_network_access(): void
    {
        $payload = $this->payload();
        $verifier = new StripeWebhookSignatureVerifier();

        $event = $verifier->verify($payload, $this->signature($payload));

        self::assertSame('evt_test_1', $event->eventId);
        self::assertSame('payment_intent.succeeded', $event->type);
        self::assertSame('pi_test_1', $event->data['id']);
        self::assertSame(7000, $event->data['amount_received']);
        self::assertSame('eur', $event->data['currency']);
    }

    #[Test]
    public function it_rejects_an_invalid_signature(): void
    {
        $payload = $this->payload();
        $timestamp = time();
        $header = sprintf('t=%d,v1=%s', $timestamp, str_repeat('0', 64));

        $this->expectException(InvalidWebhookSignature::class);

        (new StripeWebhookSignatureVerifier())->verify($payload, $header);
    }

    private function payload(): string
    {
        return json_encode([
            'id' => 'evt_test_1',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_1',
                    'object' => 'payment_intent',
                    'amount_received' => 7000,
                    'currency' => 'eur',
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function signature(string $payload): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, self::SECRET);

        return sprintf('t=%d,v1=%s', $timestamp, $signature);
    }
}
