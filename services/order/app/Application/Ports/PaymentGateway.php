<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Payments\PaymentIntentRequest;
use App\Application\Payments\ProviderPaymentIntent;
use App\Application\Payments\ProviderRefund;

interface PaymentGateway
{
    /**
     * @throws PaymentProviderUnavailable
     */
    public function createIntent(PaymentIntentRequest $request, string $idempotencyKey): ProviderPaymentIntent;

    /**
     * @throws PaymentProviderUnavailable
     */
    public function retrieveIntent(string $providerId): ProviderPaymentIntent;

    /**
     * @throws PaymentProviderUnavailable
     */
    public function refund(string $providerPaymentId, int $amount, string $idempotencyKey): ProviderRefund;
}
