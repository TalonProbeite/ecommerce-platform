<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Stripe;

use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Payments\PaymentIntentRequest;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\ProviderPaymentIntent;
use App\Application\Payments\ProviderRefund;
use App\Application\Payments\RefundStatus;
use App\Application\Ports\PaymentGateway;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;

final readonly class StripePaymentGateway implements PaymentGateway
{
    public function __construct(private StripeClient $client)
    {
    }

    public function createIntent(PaymentIntentRequest $request, string $idempotencyKey): ProviderPaymentIntent
    {
        try {
            $intent = $this->client->paymentIntents->create([
                'amount' => $request->amount,
                'currency' => strtolower($request->currency),
                'metadata' => ['order_id' => $request->orderId],
                'automatic_payment_methods' => ['enabled' => true],
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (ApiErrorException $exception) {
            throw new PaymentProviderUnavailable('Stripe PaymentIntent creation failed.', $exception);
        }

        return $this->mapIntent($intent);
    }

    public function retrieveIntent(string $providerId): ProviderPaymentIntent
    {
        try {
            $intent = $this->client->paymentIntents->retrieve($providerId);
        } catch (ApiErrorException $exception) {
            throw new PaymentProviderUnavailable('Stripe PaymentIntent retrieval failed.', $exception);
        }

        return $this->mapIntent($intent);
    }

    public function refund(string $providerPaymentId, int $amount, string $idempotencyKey): ProviderRefund
    {
        try {
            $refund = $this->client->refunds->create([
                'payment_intent' => $providerPaymentId,
                'amount' => $amount,
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (ApiErrorException $exception) {
            throw new PaymentProviderUnavailable('Stripe refund creation failed.', $exception);
        }

        return new ProviderRefund(
            providerId: $refund->id,
            status: $this->mapRefundStatus($refund),
        );
    }

    private function mapIntent(PaymentIntent $intent): ProviderPaymentIntent
    {
        return new ProviderPaymentIntent(
            providerId: $intent->id,
            status: PaymentStatus::isAllowed($intent->status)
                ? $intent->status
                : PaymentStatus::REQUIRES_PAYMENT_METHOD,
            clientSecret: $intent->client_secret,
        );
    }

    private function mapRefundStatus(Refund $refund): string
    {
        $status = $refund->status;

        return is_string($status) && RefundStatus::isAllowed($status)
            ? $status
            : RefundStatus::PENDING;
    }
}
