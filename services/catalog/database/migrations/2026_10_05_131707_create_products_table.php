<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->integer('price_per_hour');
            $table->integer('deposit_amount')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('reviews_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('store_id')->constrained()->restrictOnDelete();
            $table->uuid('category_id');
            $table->timestamps();
            $table->softDeletes();
            // Includes soft-deleted rows; a deleted product's slug cannot be reused.
            $table->unique(['store_id', 'slug']);
            $table->unique(['id', 'store_id']);
            $table->index('category_id');
            $table->foreign(['category_id', 'store_id'], 'products_category_store_foreign')
                ->references(['id', 'store_id'])->on('categories')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE products
            ADD CONSTRAINT products_price_per_hour_check CHECK (price_per_hour >= 0),
            ADD CONSTRAINT products_deposit_amount_check CHECK (deposit_amount >= 0),
            ADD CONSTRAINT products_rating_check CHECK (rating BETWEEN 0 AND 5),
            ADD CONSTRAINT products_reviews_count_check CHECK (reviews_count >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
