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
     * Cancel a PaymentIntent. When Stripe reports that the intent is already in
     * a final state (succeeded or canceled), the actual intent is retrieved
     * instead of raising an exception.
     *
     * @throws PaymentProviderUnavailable
     */
    public function cancelIntent(string $providerId, string $idempotencyKey): ProviderPaymentIntent;

    /**
     * @throws PaymentProviderUnavailable
     */
    public function refund(string $providerPaymentId, int $amount, string $idempotencyKey): ProviderRefund;
}
