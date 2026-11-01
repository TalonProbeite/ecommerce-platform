<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
