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
            $table->uuid('event_id')->default(DB::raw('uuidv7()'));
            $table->string('routing_key', 64);
            $table->string('aggregate_key', 128)->nullable();
            $table->jsonb('payload');
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('published_at', 6)->nullable();
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->index('aggregate_key', 'outbox_aggregate_key_index');
        });

        DB::statement('ALTER TABLE outbox ADD CONSTRAINT outbox_event_id_unique UNIQUE (event_id)');
        DB::statement('CREATE INDEX outbox_pending_idx ON outbox (id) WHERE published_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};
