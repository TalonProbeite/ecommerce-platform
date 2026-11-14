<?php

use App\Infrastructure\Persistence\SchemaValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('order_id');
            $table->uuid('order_item_id')->nullable();
            $table->uuid('payment_id');
            $table->string('provider_refund_id', 255)->nullable();
            $table->bigInteger('amount');
            $table->string('reason', 30);
            $table->string('status', 20);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->index('order_id', 'refunds_order_id_index');
            $table->foreign('order_id', 'refunds_order_id_foreign')
                ->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('order_item_id', 'refunds_order_item_id_foreign')
                ->references('id')->on('order_items')->restrictOnDelete();
            $table->foreign('payment_id', 'refunds_payment_id_foreign')
                ->references('id')->on('payments')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_provider_refund_id_unique
            UNIQUE (provider_refund_id)');
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_check CHECK (amount > 0)');
        DB::statement(sprintf(
            "ALTER TABLE refunds ADD CONSTRAINT refunds_reason_check CHECK (reason IN ('%s'))",
            SchemaValues::quotedList(SchemaValues::REFUND_REASON),
        ));
        DB::statement(sprintf(
            "ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status IN ('%s'))",
            SchemaValues::quotedList(SchemaValues::REFUND_STATUS),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
