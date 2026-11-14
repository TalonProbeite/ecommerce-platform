<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

final class SchemaTest extends PersistenceTestCase
{
    #[Test]
    public function all_order_tables_exist(): void
    {
        foreach ([
            'orders',
            'order_items',
            'payments',
            'refunds',
            'order_events',
            'order_comments',
            'product_snapshots',
            'outbox',
            'outbox_failed',
            'processed_events',
            'stripe_events',
        ] as $table) {
            self::assertTrue(Schema::hasTable($table), sprintf('Missing table %s.', $table));
        }
    }

    #[Test]
    public function all_time_columns_are_timestamptz_with_precision_six(): void
    {
        $expected = [
            'orders' => ['cancelled_at', 'completed_at', 'created_at', 'updated_at'],
            'order_items' => [
                'rental_start_at', 'rental_end_at', 'issued_at', 'returned_at', 'created_at', 'updated_at',
            ],
            'payments' => ['succeeded_at', 'created_at', 'updated_at'],
            'refunds' => ['created_at', 'updated_at'],
            'order_events' => ['occurred_at'],
            'order_comments' => ['created_at'],
            'product_snapshots' => ['source_occurred_at', 'deleted_at', 'created_at', 'updated_at'],
            'outbox' => ['occurred_at', 'published_at'],
            'outbox_failed' => ['occurred_at', 'published_at', 'failed_at'],
            'processed_events' => ['processed_at'],
            'stripe_events' => ['processed_at'],
        ];

        foreach ($expected as $table => $columns) {
            foreach ($columns as $column) {
                $definition = DB::table('information_schema.columns')
                    ->where('table_schema', $this->testSchema)
                    ->where('table_name', $table)
                    ->where('column_name', $column)
                    ->first();

                self::assertNotNull($definition, sprintf('Missing column %s.%s.', $table, $column));
                self::assertSame('timestamp with time zone', $definition->data_type);
                self::assertSame('6', (string) $definition->datetime_precision);
            }
        }
    }

    #[Test]
    public function named_check_and_unique_constraints_exist(): void
    {
        $constraints = collect(DB::select(
            'SELECT conname, contype FROM pg_constraint
             WHERE connamespace = (SELECT oid FROM pg_namespace WHERE nspname = current_schema())',
        ))->keyBy('conname');

        $expected = [
            'orders_customer_id_idempotency_key_unique' => 'u',
            'orders_status_check' => 'c',
            'orders_cancel_reason_check' => 'c',
            'orders_currency_check' => 'c',
            'orders_subtotal_amount_check' => 'c',
            'orders_deposit_amount_check' => 'c',
            'order_items_quantity_check' => 'c',
            'order_items_rental_window_check' => 'c',
            'order_items_billed_hours_check' => 'c',
            'order_items_price_per_hour_check' => 'c',
            'order_items_deposit_per_unit_check' => 'c',
            'order_items_line_total_check' => 'c',
            'order_items_deposit_total_check' => 'c',
            'order_items_item_status_check' => 'c',
            'order_items_returned_requires_issued_check' => 'c',
            'order_items_issued_requires_actor_check' => 'c',
            'order_items_returned_requires_actor_check' => 'c',
            'payments_provider_payment_id_unique' => 'u',
            'payments_provider_check' => 'c',
            'payments_amount_check' => 'c',
            'payments_currency_check' => 'c',
            'payments_status_check' => 'c',
            'refunds_provider_refund_id_unique' => 'u',
            'refunds_amount_check' => 'c',
            'refunds_reason_check' => 'c',
            'refunds_status_check' => 'c',
            'order_events_from_status_check' => 'c',
            'order_events_to_status_check' => 'c',
            'order_events_actor_role_check' => 'c',
            'order_comments_author_role_check' => 'c',
            'order_comments_body_check' => 'c',
            'product_snapshots_price_per_hour_check' => 'c',
            'product_snapshots_deposit_amount_check' => 'c',
            'outbox_event_id_unique' => 'u',
        ];

        foreach ($expected as $name => $type) {
            self::assertTrue($constraints->has($name), sprintf('Missing constraint %s.', $name));
            self::assertSame($type, $constraints[$name]->contype);
        }
    }

    #[Test]
    public function partial_pending_outbox_index_exists(): void
    {
        $definition = DB::table('pg_indexes')
            ->where('schemaname', $this->testSchema)
            ->where('indexname', 'outbox_pending_idx')
            ->value('indexdef');

        self::assertIsString($definition);
        self::assertStringContainsString('(id)', $definition);
        self::assertStringContainsString('published_at IS NULL', $definition);
    }
}
