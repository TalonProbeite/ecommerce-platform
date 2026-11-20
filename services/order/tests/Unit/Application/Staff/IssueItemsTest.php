<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Staff;

use App\Application\Exceptions\OrderNotFound;
use App\Application\Staff\IssueItems\IssueItems;
use App\Domain\Events\OrderIssued;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Domain\Exceptions\ItemNotInOrder;
use App\Domain\Order\ItemStatus;
use App\Domain\Order\OrderStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\Unit\Application\OrderApplicationTestCase;

final class IssueItemsTest extends OrderApplicationTestCase
{
    private const string ITEM_ONE = '01930000-0000-7000-8000-000000000101';
    private const string ITEM_TWO = '01930000-0000-7000-8000-000000000102';

    private IssueItems $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new IssueItems(
            new FakeTransactionRunner(),
            $this->orders,
            $this->clock,
        );
    }

    #[Test]
    public function it_issues_reserved_items_and_moves_a_paid_order_to_in_rental(): void
    {
        $this->seedOrder(OrderStatus::PAID, [$this->makeItem(self::ITEM_ONE)]);

        $result = $this->useCase->execute(
            $this->manager(),
            self::ORDER_ID,
            [self::ITEM_ONE],
        );

        self::assertSame([self::ITEM_ONE], $result->processedItemIds);
        self::assertSame([], $result->skippedItemIds);
        self::assertSame(OrderStatus::IN_RENTAL, $result->order->status());

        $item = $result->order->items()[0];

        self::assertSame(ItemStatus::ISSUED, $item->status());
        self::assertNotNull($item->issuedAt());
        self::assertSame($this->manager()->id, $item->issuedBy());

        $events = $this->outbox->eventsOf(OrderIssued::class);

        self::assertCount(1, $events);
        self::assertSame([[
            'line_id' => self::ITEM_ONE,
            'product_id' => '33333333-3333-4333-8333-333333333333',
            'quantity' => 1,
        ]], $events[0]->items());
    }

    #[Test]
    public function it_skips_already_issued_items_on_repeat(): void
    {
        $this->seedOrder(OrderStatus::PAID, [$this->makeItem(self::ITEM_ONE)]);

        $first = $this->useCase->execute($this->manager(), self::ORDER_ID, [self::ITEM_ONE]);
        $version = $first->order->version();

        $second = $this->useCase->execute($this->manager(), self::ORDER_ID, [self::ITEM_ONE]);

        self::assertSame([], $second->processedItemIds);
        self::assertSame([self::ITEM_ONE], $second->skippedItemIds);
        self::assertSame($version, $second->order->version());
        self::assertCount(1, $this->outbox->eventsOf(OrderIssued::class));
    }

    #[Test]
    public function it_writes_order_issued_only_for_items_processed_in_this_call(): void
    {
        $this->seedOrder(OrderStatus::PAID, [
            $this->makeItem(self::ITEM_ONE),
            $this->makeItem(self::ITEM_TWO),
        ]);

        $this->useCase->execute($this->manager(), self::ORDER_ID, [self::ITEM_ONE]);
        $this->outbox->reset();

        $result = $this->useCase->execute(
            $this->manager(),
            self::ORDER_ID,
            [self::ITEM_ONE, self::ITEM_TWO],
        );

        self::assertSame([self::ITEM_TWO], $result->processedItemIds);
        self::assertSame([self::ITEM_ONE], $result->skippedItemIds);

        $events = $this->outbox->eventsOf(OrderIssued::class);

        self::assertCount(1, $events);
        self::assertSame([[
            'line_id' => self::ITEM_TWO,
            'product_id' => '33333333-3333-4333-8333-333333333333',
            'quantity' => 1,
        ]], $events[0]->items());
    }

    #[Test]
    public function it_rejects_an_item_from_another_order(): void
    {
        $this->seedOrder(OrderStatus::PAID, [$this->makeItem(self::ITEM_ONE)]);

        $this->expectException(ItemNotInOrder::class);

        $this->useCase->execute(
            $this->manager(),
            self::ORDER_ID,
            ['01930000-0000-7000-8000-000000000999'],
        );
    }

    #[Test]
    public function it_rejects_issuing_a_created_order(): void
    {
        $this->seedOrder(OrderStatus::CREATED, [$this->makeItem(self::ITEM_ONE)]);

        $this->expectException(InvalidOrderTransition::class);

        $this->useCase->execute($this->manager(), self::ORDER_ID, [self::ITEM_ONE]);
    }

    #[Test]
    public function it_hides_an_order_from_a_manager_of_another_store(): void
    {
        $this->seedOrder(OrderStatus::PAID, [$this->makeItem(self::ITEM_ONE)]);

        $this->expectException(OrderNotFound::class);

        $this->useCase->execute(
            $this->manager('22222222-2222-4222-8222-999999999999'),
            self::ORDER_ID,
            [self::ITEM_ONE],
        );
    }
}
