<?php

declare(strict_types=1);

namespace App\Application\Payments\Webhooks;

use App\Application\Exceptions\PaymentNotFound;
use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Exceptions\RetryableException;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\StripeEvent;
use App\Application\Ports\PaymentRepository;
use App\Application\Ports\StripeEventStore;
use App\Application\Ports\TransactionRunnerInterface;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

final readonly class HandleStripeEvent
{
    public const string PAYMENT_INTENT_SUCCEEDED = 'payment_intent.succeeded';
    public const string PAYMENT_INTENT_PAYMENT_FAILED = 'payment_intent.payment_failed';
    public const string PAYMENT_INTENT_CANCELED = 'payment_intent.canceled';

    public function __construct(
        private TransactionRunnerInterface $transactions,
        private StripeEventStore $events,
        private OnPaymentSucceeded $onPaymentSucceeded,
        private OnPaymentFailed $onPaymentFailed,
        private PaymentRepository $payments,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(StripeEvent $event, CarbonImmutable $now): void
    {
        $this->transactions->run(function () use ($event, $now): void {
            if (! $this->events->markProcessed($event->eventId, $event->type, $now)) {
                return;
            }

            try {
                match ($event->type) {
                    self::PAYMENT_INTENT_SUCCEEDED => $this->onPaymentSucceeded->handle($event, $now),
                    self::PAYMENT_INTENT_PAYMENT_FAILED => $this->onPaymentFailed->handle($event, $now),
                    self::PAYMENT_INTENT_CANCELED => $this->onPaymentCanceled($event, $now),
                    default => $this->logger->info('Ignoring an unsupported Stripe event type.', [
                        'event_id' => $event->eventId,
                        'type' => $event->type,
                    ]),
                };
            } catch (PaymentNotFound|PaymentProviderUnavailable $exception) {
                throw new RetryableException($exception->getMessage(), $exception);
            }
        });
    }

    private function onPaymentCanceled(StripeEvent $event, CarbonImmutable $now): void
    {
        $providerPaymentId = $event->data['id'] ?? null;

        if (! is_string($providerPaymentId) || $providerPaymentId === '') {
            throw new RetryableException(sprintf(
                'Stripe event "%s" does not contain a payment id.',
                $event->eventId,
            ));
        }

        $payment = $this->payments->findByProviderPaymentIdForUpdate($providerPaymentId);

        if ($payment === null) {
            throw new PaymentNotFound($providerPaymentId);
        }

        $this->payments->updateStatus($payment->id, PaymentStatus::CANCELED, $now);
    }
}
