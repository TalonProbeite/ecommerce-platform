<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // order_line_id is NOT NULL and there is no safe backfill for existing rows.
        if (DB::table('stock_reservations')->exists()) {
            throw new \RuntimeException(
                'Cannot add stock_reservations.order_line_id: the table is not empty. '
                . 'Truncate the table or provide an explicit backfill before running this migration.',
            );
        }

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->index('order_id', 'stock_reservations_order_id_index');
            // The unique index also provided order_id lookups; replace it with a plain index above.
            $table->dropUnique('stock_reservations_order_id_product_id_unique');

            $table->uuid('order_line_id');
            $table->unique('order_line_id', 'stock_reservations_order_line_id_unique');
        });

        Schema::create('processed_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('routing_key', 64);
            $table->timestampTz('processed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropUnique('stock_reservations_order_line_id_unique');
            $table->dropIndex('stock_reservations_order_id_index');
            $table->dropColumn('order_line_id');

            $table->unique(['order_id', 'product_id'], 'stock_reservations_order_id_product_id_unique');
        });
    }
};
