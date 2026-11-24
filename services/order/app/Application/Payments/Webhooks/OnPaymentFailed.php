<?php

declare(strict_types=1);

namespace App\Application\Payments\Webhooks;

use App\Application\Exceptions\PaymentNotFound;
use App\Application\Exceptions\RetryableException;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\StripeEvent;
use App\Application\Ports\PaymentRepository;
use App\Application\Ports\TransactionRunnerInterface;
use Carbon\CarbonImmutable;

final readonly class OnPaymentFailed
{
    public function __construct(
        private TransactionRunnerInterface $transactions,
        private PaymentRepository $payments,
    ) {
    }

    public function handle(StripeEvent $event, CarbonImmutable $now): void
    {
        $this->transactions->run(function () use ($event, $now): void {
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

            $status = $event->data['status'] ?? null;
            $status = is_string($status) && PaymentStatus::isAllowed($status)
                ? $status
                : PaymentStatus::REQUIRES_PAYMENT_METHOD;

            $this->payments->updateStatus(
                $payment->id,
                $status,
                $now,
                null,
                $this->failureCode($event),
            );
        });
    }

    private function failureCode(StripeEvent $event): ?string
    {
        $lastPaymentError = $event->data['last_payment_error'] ?? null;

        if (! is_array($lastPaymentError)) {
            return null;
        }

        $code = $lastPaymentError['code'] ?? null;

        if (! is_string($code) || $code === '') {
            return null;
        }

        return mb_substr($code, 0, 100);
    }
}
