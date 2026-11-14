<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('outbox_failed', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('event_id')->default(DB::raw('uuidv7()'));
            $table->string('routing_key', 64);
            $table->string('aggregate_key', 128)->nullable();
            $table->jsonb('payload');
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('published_at', 6)->nullable();
            $table->timestampTz('failed_at', 6);
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->index('event_id', 'outbox_failed_event_id_index');
            $table->index('aggregate_key', 'outbox_failed_aggregate_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_failed');
    }
};
