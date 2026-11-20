<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('product_snapshots', function (Blueprint $table) {
            $table->uuid('product_id')->primary();
            $table->uuid('store_id');
            $table->uuid('category_id');
            $table->string('name', 255);
            $table->bigInteger('price_per_hour');
            $table->bigInteger('deposit_amount');
            $table->boolean('is_active');
            $table->timestampTz('source_occurred_at', 6);
            $table->timestampTz('deleted_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->index('store_id', 'product_snapshots_store_id_index');
        });

        DB::statement('ALTER TABLE product_snapshots ADD CONSTRAINT product_snapshots_price_per_hour_check
            CHECK (price_per_hour >= 0)');
        DB::statement('ALTER TABLE product_snapshots ADD CONSTRAINT product_snapshots_deposit_amount_check
            CHECK (deposit_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_snapshots');
    }
};
