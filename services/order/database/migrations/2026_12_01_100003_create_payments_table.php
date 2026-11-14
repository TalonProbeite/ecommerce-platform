<?php

use App\Infrastructure\Persistence\SchemaValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('order_id');
            $table->string('provider', 20);
            $table->string('provider_payment_id', 255);
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 30);
            $table->string('failure_code', 100)->nullable();
            $table->timestampTz('succeeded_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->index('order_id', 'payments_order_id_index');
            $table->foreign('order_id', 'payments_order_id_foreign')
                ->references('id')->on('orders')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_provider_payment_id_unique
            UNIQUE (provider_payment_id)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_provider_check CHECK (provider = 'stripe')");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_currency_check CHECK (currency = 'EUR')");
        DB::statement(sprintf(
            "ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('%s'))",
            SchemaValues::quotedList(SchemaValues::PAYMENT_STATUS),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
