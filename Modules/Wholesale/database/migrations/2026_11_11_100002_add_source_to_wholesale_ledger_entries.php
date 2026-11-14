<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What posted a ledger entry the platform wrote on its own, beyond invoices and credit notes:
 * `order_total` (a delivered order's total changed), `cash_collected` (cash on delivery marked
 * paid), `cash_reversed` and `cash_recollected` (marked unpaid, then paid again). Null for the rest.
 * A cash on delivery order is recorded as collected once.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('wholesale.table_prefix');

        Schema::table($prefix.'ledger_entries', function (Blueprint $table) {
            $table->string('source', 30)->nullable();
        });

        DB::statement("CREATE UNIQUE INDEX {$prefix}ledger_one_cash_payment_per_order ON {$prefix}ledger_entries (order_id) WHERE source = 'cash_collected'");
    }

    public function down(): void
    {
        $prefix = config('wholesale.table_prefix');

        DB::statement("DROP INDEX IF EXISTS {$prefix}ledger_one_cash_payment_per_order");

        Schema::table($prefix.'ledger_entries', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
