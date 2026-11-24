<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('outbox', function (Blueprint $table) {
            $table->string('aggregate_key', 128)->nullable();
            $table->index('aggregate_key', 'outbox_aggregate_key_index');
        });

        // Backfill ordering keys for rows created before the column existed.
        DB::statement(<<<'SQL'
            UPDATE outbox
            SET aggregate_key = CASE
                WHEN routing_key = 'stock.changed' THEN payload->>'product_id'
                WHEN routing_key IN ('stock.reserved', 'stock.reservation_failed') THEN payload->>'order_id'
            END
            WHERE aggregate_key IS NULL
        SQL);

        Schema::create('outbox_failed', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('event_id');
            $table->string('routing_key', 64);
            $table->string('aggregate_key', 128)->nullable();
            $table->jsonb('payload');
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('failed_at', 6);
            $table->integer('attempts');
            $table->text('last_error');

            $table->index('event_id', 'outbox_failed_event_id_index');
            $table->index('aggregate_key', 'outbox_failed_aggregate_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_failed');

        Schema::table('outbox', function (Blueprint $table) {
            $table->dropIndex('outbox_aggregate_key_index');
            $table->dropColumn('aggregate_key');
        });
    }
};
