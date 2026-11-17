<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Application\Exceptions\InvalidWebhookSignature;
use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Exceptions\RetryableException;
use App\Application\Payments\Webhooks\HandleStripeEvent;
use App\Application\Ports\Clock;
use App\Application\Ports\WebhookSignatureVerifier;
use App\Http\Controllers\Controller;
use App\Http\Responses\ProblemResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class StripeWebhookController extends Controller
{
    public function __construct(
        private WebhookSignatureVerifier $verifier,
        private HandleStripeEvent $handleStripeEvent,
        private Clock $clock,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $signatureHeader = $request->header('Stripe-Signature');

        if (! is_string($signatureHeader) || $signatureHeader === '') {
            return ProblemResponse::make(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                'The Stripe-Signature header is required.',
            );
        }

        try {
            $event = $this->verifier->verify($request->getContent(), $signatureHeader);
        } catch (InvalidWebhookSignature) {
            return ProblemResponse::make(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                'The Stripe webhook signature is invalid.',
            );
        } catch (PaymentProviderUnavailable) {
            return ProblemResponse::make(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Internal Server Error',
                'The Stripe webhook secret is not configured.',
            );
        }

        try {
            $this->handleStripeEvent->handle($event, $this->clock->now());
        } catch (RetryableException|PaymentProviderUnavailable) {
            return ProblemResponse::make(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Internal Server Error',
                'The Stripe event could not be processed.',
            );
        }

        return new JsonResponse(['received' => true], Response::HTTP_OK);
    }
}
