<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Return and exchange requests on an order: the request (type, status, reason, notes, the refund
 * recorded), its lines (an order line, how many, and for an exchange the product given instead),
 * and its status history. An order has at most one open request: a partial unique index holds it
 * (PostgreSQL), and ReturnRequestService checks it on the locked order.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('items.table_prefix');

        Schema::create($prefix.'return_requests', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('order_id')->index()->constrained($prefix.'orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->index();
            $table->string('reason', 30);
            $table->text('customer_note')->nullable();
            $table->text('staff_note')->nullable();
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->boolean('refunded')->default(false);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX {$prefix}return_requests_one_open ON {$prefix}return_requests (order_id) WHERE status IN ('requested', 'approved', 'received')");

        Schema::create($prefix.'return_request_lines', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('return_request_id')->index()->constrained($prefix.'return_requests')->cascadeOnDelete();
            $table->foreignId('order_item_id')->index()->constrained($prefix.'order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->foreignId('exchange_product_id')->nullable()->index()->constrained($prefix.'products')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create($prefix.'return_request_history', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('return_request_id')->index()->constrained($prefix.'return_requests')->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('items.table_prefix');

        Schema::dropIfExists($prefix.'return_request_history');
        Schema::dropIfExists($prefix.'return_request_lines');
        Schema::dropIfExists($prefix.'return_requests');
    }
};
