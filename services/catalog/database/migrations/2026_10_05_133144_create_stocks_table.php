<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            
            $table->integer('quantity')->default(0);
            $table->integer('reserved')->default(0);
            
            $table->timestamps();
        });

        DB::statement('ALTER TABLE stocks ADD CONSTRAINT check_quantity_positive CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE stocks ADD CONSTRAINT check_reserved_positive CHECK (reserved >= 0)');
        DB::statement('ALTER TABLE stocks ADD CONSTRAINT check_reserve_overflow CHECK (quantity >= reserved)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};