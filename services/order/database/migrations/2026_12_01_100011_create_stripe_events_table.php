<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stripe_events', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('type', 100);
            $table->timestampTz('processed_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_events');
    }
};
