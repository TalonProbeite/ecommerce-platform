<?php

use App\Infrastructure\Persistence\SchemaValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('order_id');
            $table->uuid('order_item_id')->nullable();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->uuid('actor_id')->nullable();
            $table->string('actor_role', 20);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('occurred_at', 6);

            $table->index('order_id', 'order_events_order_id_index');
            $table->index(['order_id', 'occurred_at'], 'order_events_order_id_occurred_at_index');
            $table->foreign('order_id', 'order_events_order_id_foreign')
                ->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('order_item_id', 'order_events_order_item_id_foreign')
                ->references('id')->on('order_items')->restrictOnDelete();
        });

        DB::statement(sprintf(
            "ALTER TABLE order_events ADD CONSTRAINT order_events_from_status_check
                CHECK (from_status IS NULL OR from_status IN ('%s'))",
            SchemaValues::quotedList(SchemaValues::ORDER_STATUS),
        ));
        DB::statement(sprintf(
            "ALTER TABLE order_events ADD CONSTRAINT order_events_to_status_check
                CHECK (to_status IN ('%s'))",
            SchemaValues::quotedList(SchemaValues::ORDER_STATUS),
        ));
        DB::statement(sprintf(
            "ALTER TABLE order_events ADD CONSTRAINT order_events_actor_role_check
                CHECK (actor_role IN ('%s'))",
            SchemaValues::quotedList(SchemaValues::ACTOR_ROLE),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
