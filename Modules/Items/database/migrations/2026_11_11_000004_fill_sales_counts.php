<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Items\Services\SalesCounter;

/**
 * Fills `sales_count` from the orders placed before it existed (SalesCounter::recount, as
 * `items:recount-sales` does). Safe to run again; nothing to undo.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(SalesCounter::class)->recount();
    }

    public function down(): void
    {
        // The column goes with its own migration.
    }
};
