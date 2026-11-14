<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An open cart is one that was never converted: a converted cart whose order was later deleted for
 * good loses its `order_id` (nullOnDelete) but must stay closed. "One open cart per user" now
 * counts carts with neither an order nor a conversion date.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('wholesale.table_prefix');

        DB::statement("DROP INDEX IF EXISTS {$prefix}carts_one_open");
        DB::statement("CREATE UNIQUE INDEX {$prefix}carts_one_open ON {$prefix}carts (user_id) WHERE order_id IS NULL AND converted_at IS NULL");
    }

    public function down(): void
    {
        $prefix = config('wholesale.table_prefix');

        DB::statement("DROP INDEX IF EXISTS {$prefix}carts_one_open");
        DB::statement("CREATE UNIQUE INDEX {$prefix}carts_one_open ON {$prefix}carts (user_id) WHERE order_id IS NULL");
    }
};
