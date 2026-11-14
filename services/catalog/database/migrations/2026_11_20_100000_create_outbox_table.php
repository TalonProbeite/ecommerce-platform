<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('event_id')->unique();
            $table->string('routing_key', 64);
            $table->jsonb('payload');
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('published_at', 6)->nullable();
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
        });

        DB::statement('CREATE INDEX outbox_pending_idx ON outbox (id) WHERE published_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};
