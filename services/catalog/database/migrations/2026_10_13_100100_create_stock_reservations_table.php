<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Order Service owns orders; this is deliberately not a foreign key.
            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->foreignUuid('store_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->string('status', 30);
            $table->timestampTz('rental_start_at')->nullable();
            $table->timestampTz('rental_end_at')->nullable();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('returned_at')->nullable();
            $table->timestamps();

            $table->foreign(['product_id', 'store_id'], 'stock_reservations_product_store_foreign')
                ->references(['id', 'store_id'])->on('products')->restrictOnDelete();
            // This index also supports lookups by its leading order_id column.
            $table->unique(['order_id', 'product_id']);
            $table->index('product_id');
            $table->index(['store_id', 'status']);
        });

        DB::statement("ALTER TABLE stock_reservations
            ADD CONSTRAINT stock_reservations_quantity_check CHECK (quantity > 0),
            ADD CONSTRAINT stock_reservations_status_check CHECK (status IN ('reserved', 'issued', 'released', 'returned'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
