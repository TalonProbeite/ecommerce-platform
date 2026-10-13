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
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id');
            // PostgreSQL requires the referenced key before the self-referencing FK.
            $table->primary('id');
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->uuid('parent_id')->nullable();
            $table->foreignUuid('store_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'slug']);
            $table->unique(['id', 'store_id']);
            $table->index(['parent_id', 'store_id']);
        });

        // The referenced UNIQUE must exist before the self-referencing composite FK.
        // Null only parent_id on deletion, keeping the child's company unchanged.
        DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_parent_store_foreign
            FOREIGN KEY (parent_id, store_id) REFERENCES categories (id, store_id)
            ON DELETE SET NULL (parent_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
