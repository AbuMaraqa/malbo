<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant finance (phase 6B): each merchant's account, one row per entry. `amount` is signed: + the
 * merchant owes more (an invoice, an adjustment up), − a payment, a credit note or an adjustment
 * down; the balance is their sum. An order is invoiced once; a return request is credited once.
 * And the merchant's credit: whether it may buy on credit, and up to how much.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('wholesale.table_prefix');
        $items = config('items.table_prefix');

        Schema::create($prefix.'ledger_entries', function (Blueprint $table) use ($prefix, $items) {
            $table->id();
            $table->foreignId('merchant_id')->constrained($prefix.'merchants')->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->decimal('amount', 12, 2);
            $table->foreignId('order_id')->nullable()->index()->constrained($items.'orders')->nullOnDelete();
            $table->foreignId('return_request_id')->nullable()->index()->constrained($items.'return_requests')->nullOnDelete();
            $table->string('method', 20)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name')->nullable();
            $table->date('entry_date');
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['merchant_id', 'entry_date', 'id']);
        });

        DB::statement("CREATE UNIQUE INDEX {$prefix}ledger_one_invoice_per_order ON {$prefix}ledger_entries (order_id) WHERE type = 'invoice'");
        DB::statement("CREATE UNIQUE INDEX {$prefix}ledger_one_credit_per_return ON {$prefix}ledger_entries (return_request_id) WHERE return_request_id IS NOT NULL");

        Schema::table($prefix.'merchants', function (Blueprint $table) {
            $table->boolean('credit_enabled')->default(false);
            $table->decimal('credit_limit', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        $prefix = config('wholesale.table_prefix');

        Schema::table($prefix.'merchants', function (Blueprint $table) {
            $table->dropColumn(['credit_enabled', 'credit_limit']);
        });

        Schema::dropIfExists($prefix.'ledger_entries');
    }
};
