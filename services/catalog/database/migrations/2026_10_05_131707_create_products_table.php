<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('slug',255);
            $table->text('description')->nullable();
            $table->integer('price');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('store_id')->constrained();
            $table->foreignUuid('category_id')->constrained();
            $table->timestamps();
            $table->softDeletes();


            $table->index(['store_id', 'slug']);
            $table->index('category_id');
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
