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
use Stripe\ErrorObject;
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

    public function cancelIntent(string $providerId, string $idempotencyKey): ProviderPaymentIntent
    {
        try {
            $intent = $this->client->paymentIntents->cancel($providerId, [], [
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (ApiErrorException $exception) {
            if ($this->isAlreadyFinalState($exception)) {
                return $this->retrieveIntent($providerId);
            }

            throw new PaymentProviderUnavailable('Stripe PaymentIntent cancellation failed.', $exception);
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

    private function isAlreadyFinalState(ApiErrorException $exception): bool
    {
        $error = $exception->getError();

        if ($error !== null && $error->code === ErrorObject::CODE_PAYMENT_INTENT_UNEXPECTED_STATE) {
            return true;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'status of succeeded')
            || str_contains($message, 'status of canceled');
    }

    private function mapIntent(PaymentIntent $intent): ProviderPaymentIntent
    {
        $status = match ($intent->status) {
            PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD => PaymentStatus::REQUIRES_PAYMENT_METHOD,
            PaymentIntent::STATUS_REQUIRES_CONFIRMATION => PaymentStatus::REQUIRES_CONFIRMATION,
            PaymentIntent::STATUS_REQUIRES_ACTION => PaymentStatus::REQUIRES_ACTION,
            PaymentIntent::STATUS_PROCESSING => PaymentStatus::PROCESSING,
            PaymentIntent::STATUS_REQUIRES_CAPTURE => PaymentStatus::REQUIRES_CAPTURE,
            PaymentIntent::STATUS_SUCCEEDED => PaymentStatus::SUCCEEDED,
            PaymentIntent::STATUS_CANCELED => PaymentStatus::CANCELED,
            default => throw new PaymentProviderUnavailable(sprintf(
                'Stripe returned an unsupported PaymentIntent status "%s".',
                $intent->status,
            )),
        };

        return new ProviderPaymentIntent(
            providerId: $intent->id,
            status: $status,
            clientSecret: $intent->client_secret,
        );
    }

    /**
     * Stripe refund statuses are pending, requires_action, succeeded, failed and canceled.
     * pending and requires_action both map to RefundStatus::PENDING.
     */
    private function mapRefundStatus(Refund $refund): string
    {
        $status = $refund->status;

        return is_string($status) && RefundStatus::isAllowed($status)
            ? $status
            : RefundStatus::PENDING;
    }
}
