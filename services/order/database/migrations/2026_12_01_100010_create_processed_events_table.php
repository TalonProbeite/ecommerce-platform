<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('processed_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('routing_key', 64);
            $table->timestampTz('processed_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');
    }
};
