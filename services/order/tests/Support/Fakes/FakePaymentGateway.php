<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Payments\PaymentIntentRequest;
use App\Application\Payments\ProviderPaymentIntent;
use App\Application\Payments\ProviderRefund;
use App\Application\Ports\PaymentGateway;

final class FakePaymentGateway implements PaymentGateway
{
    /**
     * @var list<array{request: PaymentIntentRequest, idempotencyKey: string}>
     */
    public array $createCalls = [];

    /**
     * @var list<string>
     */
    public array $retrieveCalls = [];

    /**
     * @var list<array{providerPaymentId: string, amount: int, idempotencyKey: string}>
     */
    public array $refundCalls = [];

    public ProviderPaymentIntent $intent;

    public ProviderPaymentIntent $retrievedIntent;

    public ProviderRefund $refund;

    public ?PaymentProviderUnavailable $createFailure = null;

    public ?PaymentProviderUnavailable $retrieveFailure = null;

    public ?PaymentProviderUnavailable $refundFailure = null;

    public function __construct()
    {
        $this->intent = new ProviderPaymentIntent('pi_test_1', 'requires_payment_method', 'cs_test_1');
        $this->retrievedIntent = $this->intent;
        $this->refund = new ProviderRefund('re_test_1', 'succeeded');
    }

    public function createIntent(PaymentIntentRequest $request, string $idempotencyKey): ProviderPaymentIntent
    {
        $this->createCalls[] = ['request' => $request, 'idempotencyKey' => $idempotencyKey];

        if ($this->createFailure !== null) {
            throw $this->createFailure;
        }

        return $this->intent;
    }

    public function retrieveIntent(string $providerId): ProviderPaymentIntent
    {
        $this->retrieveCalls[] = $providerId;

        if ($this->retrieveFailure !== null) {
            throw $this->retrieveFailure;
        }

        return $this->retrievedIntent;
    }

    public function refund(string $providerPaymentId, int $amount, string $idempotencyKey): ProviderRefund
    {
        $this->refundCalls[] = [
            'providerPaymentId' => $providerPaymentId,
            'amount' => $amount,
            'idempotencyKey' => $idempotencyKey,
        ];

        if ($this->refundFailure !== null) {
            throw $this->refundFailure;
        }

        return $this->refund;
    }
}
