<?php

use App\Infrastructure\Persistence\SchemaValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->string('product_name', 255);
            $table->integer('quantity');
            $table->timestampTz('rental_start_at', 6);
            $table->timestampTz('rental_end_at', 6);
            $table->integer('billed_hours');
            $table->bigInteger('price_per_hour');
            $table->bigInteger('deposit_per_unit');
            $table->bigInteger('line_total');
            $table->bigInteger('deposit_total');
            $table->string('item_status', 20);
            $table->timestampTz('issued_at', 6)->nullable();
            $table->uuid('issued_by')->nullable();
            $table->timestampTz('returned_at', 6)->nullable();
            $table->uuid('returned_by')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->index('order_id', 'order_items_order_id_index');
            $table->index('product_id', 'order_items_product_id_index');
            $table->foreign('order_id', 'order_items_order_id_foreign')
                ->references('id')->on('orders')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_check
            CHECK (quantity BETWEEN 1 AND 1000)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_rental_window_check
            CHECK (rental_end_at > rental_start_at)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_billed_hours_check
            CHECK (billed_hours > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_price_per_hour_check
            CHECK (price_per_hour >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_deposit_per_unit_check
            CHECK (deposit_per_unit >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_line_total_check
            CHECK (line_total >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_deposit_total_check
            CHECK (deposit_total >= 0)');
        DB::statement(sprintf(
            "ALTER TABLE order_items ADD CONSTRAINT order_items_item_status_check
                CHECK (item_status IN ('%s'))",
            SchemaValues::quotedList(SchemaValues::ITEM_STATUS),
        ));
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_returned_requires_issued_check
            CHECK (returned_at IS NULL OR issued_at IS NOT NULL)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_issued_requires_actor_check
            CHECK (issued_at IS NULL OR issued_by IS NOT NULL)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_returned_requires_actor_check
            CHECK (returned_at IS NULL OR returned_by IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
