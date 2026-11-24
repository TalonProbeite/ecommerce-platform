<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Exceptions\InvalidWebhookSignature;
use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Payments\StripeEvent;

interface WebhookSignatureVerifier
{
    /**
     * @throws InvalidWebhookSignature
     * @throws PaymentProviderUnavailable
     */
    public function verify(string $payload, string $signatureHeader): StripeEvent;
}
