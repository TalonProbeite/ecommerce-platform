<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Payments;

use App\Application\Payments\PaymentRecord;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\StripeEvent;
use App\Application\Payments\Webhooks\OnPaymentFailed;
use App\Domain\Order\OrderStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeOutboxWriter;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\InMemoryOrderRepository;
use Tests\Support\Fakes\InMemoryPaymentRepository;
use Tests\TestCase;
use Tests\Unit\Domain\OrderFixtures;

final class OnPaymentFailedTest extends TestCase
{
    use OrderFixtures;

    private CarbonImmutable $now;
    private InMemoryOrderRepository $orders;
    private InMemoryPaymentRepository $payments;
    private OnPaymentFailed $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-11-20T12:00:00.123456+00:00');
        $clock = new FixedClock($this->now);
        $this->orders = new InMemoryOrderRepository(new FakeOutboxWriter(), $clock);
        $this->payments = new InMemoryPaymentRepository();
        $this->useCase = new OnPaymentFailed(new FakeTransactionRunner(), $this->payments);

        $order = $this->makeOrder();
        $order->markStockReserved($this->now);
        $order->markPaymentPending($this->now);
        $this->orders->save($order);

        $this->payments->insert(new PaymentRecord(
            id: '01930000-0000-7000-8000-0000000000f1',
            orderId: self::ORDER_ID,
            provider: 'stripe',
            providerPaymentId: 'pi_test_1',
            amount: 7000,
            currency: 'EUR',
            status: PaymentStatus::PROCESSING,
            failureCode: null,
            succeededAt: null,
            createdAt: $this->now,
            updatedAt: $this->now,
        ));
    }

    #[Test]
    public function it_records_the_failed_status_and_code_without_changing_the_order(): void
    {
        $this->useCase->handle($this->event('card_declined'), $this->now);

        $payment = $this->payments->payments()[0];

        self::assertSame(PaymentStatus::REQUIRES_PAYMENT_METHOD, $payment->status);
        self::assertSame('card_declined', $payment->failureCode);
        self::assertNull($payment->succeededAt);
        self::assertSame(OrderStatus::PAYMENT_PENDING, $this->orders->findById(self::ORDER_ID)?->status());
    }

    #[Test]
    public function it_truncates_a_long_failure_code_to_one_hundred_characters(): void
    {
        $this->useCase->handle($this->event(str_repeat('a', 120)), $this->now);

        self::assertSame(100, strlen((string) $this->payments->payments()[0]->failureCode));
    }

    private function event(string $code): StripeEvent
    {
        return new StripeEvent(
            eventId: 'evt_failed_1',
            type: 'payment_intent.payment_failed',
            data: [
                'id' => 'pi_test_1',
                'status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
                'last_payment_error' => ['code' => $code],
            ],
        );
    }
}
