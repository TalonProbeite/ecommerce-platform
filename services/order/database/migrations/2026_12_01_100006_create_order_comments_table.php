<?php

use App\Infrastructure\Persistence\SchemaValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('order_comments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('order_id');
            $table->uuid('author_id');
            $table->string('author_role', 20);
            $table->text('body');
            $table->timestampTz('created_at', 6)->useCurrent();

            $table->index('order_id', 'order_comments_order_id_index');
            $table->foreign('order_id', 'order_comments_order_id_foreign')
                ->references('id')->on('orders')->cascadeOnDelete();
        });

        DB::statement(sprintf(
            "ALTER TABLE order_comments ADD CONSTRAINT order_comments_author_role_check
                CHECK (author_role IN ('%s'))",
            SchemaValues::quotedList(SchemaValues::ACTOR_ROLE),
        ));
        DB::statement('ALTER TABLE order_comments ADD CONSTRAINT order_comments_body_check
            CHECK (char_length(body) BETWEEN 1 AND 2000)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_comments');
    }
};
