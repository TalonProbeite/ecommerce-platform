<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Stripe;

use App\Application\Exceptions\InvalidWebhookSignature;
use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Payments\StripeEvent;
use App\Application\Ports\WebhookSignatureVerifier;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

final readonly class StripeWebhookSignatureVerifier implements WebhookSignatureVerifier
{
    public function verify(string $payload, string $signatureHeader): StripeEvent
    {
        $secret = config('stripe.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            throw new PaymentProviderUnavailable('Stripe webhook secret is not configured.');
        }

        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $secret);
        } catch (SignatureVerificationException|UnexpectedValueException $exception) {
            throw new InvalidWebhookSignature('The Stripe webhook signature is invalid.', $exception);
        }

        return new StripeEvent(
            eventId: $event->id,
            type: $event->type,
            data: $event->data->object->toArray(),
        );
    }
}
