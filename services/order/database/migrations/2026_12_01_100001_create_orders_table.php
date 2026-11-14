<?php

use App\Infrastructure\Persistence\SchemaValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('customer_id');
            $table->uuid('store_id');
            $table->string('status', 20);
            $table->string('cancel_reason', 30)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->bigInteger('subtotal_amount');
            $table->bigInteger('deposit_amount');
            $table->string('idempotency_key', 64);
            $table->char('request_fingerprint', 64);
            $table->integer('version')->default(1);
            $table->timestampTz('cancelled_at', 6)->nullable();
            $table->timestampTz('completed_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->index('customer_id', 'orders_customer_id_index');
            $table->index('store_id', 'orders_store_id_index');
            $table->index(['store_id', 'status'], 'orders_store_id_status_index');
        });

        DB::statement('CREATE INDEX orders_customer_id_created_at_index
            ON orders (customer_id, created_at DESC)');

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_customer_id_idempotency_key_unique
            UNIQUE (customer_id, idempotency_key)');

        DB::statement(sprintf(
            "ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('%s'))",
            SchemaValues::quotedList(SchemaValues::ORDER_STATUS),
        ));

        DB::statement(sprintf(
            "ALTER TABLE orders ADD CONSTRAINT orders_cancel_reason_check
                CHECK (cancel_reason IS NULL OR cancel_reason IN ('%s'))",
            SchemaValues::quotedList(SchemaValues::CANCEL_REASON),
        ));

        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_currency_check CHECK (currency = 'EUR')");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_subtotal_amount_check CHECK (subtotal_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_deposit_amount_check CHECK (deposit_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
