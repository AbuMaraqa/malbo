<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Incoming goods (phase 6B): how many pieces of a sellable product are on their way and when they
 * are expected. Merchants may reserve them (a B2B sale can take the stock below 0, down to minus
 * the incoming pieces); an order line records how many of its pieces were such a reservation.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('items.table_prefix');

        Schema::table($prefix.'products', function (Blueprint $table) {
            $table->integer('incoming_quantity')->default(0);
            $table->date('incoming_expected_at')->nullable();
        });

        Schema::table($prefix.'order_items', function (Blueprint $table) {
            $table->integer('preorder_quantity')->nullable();
        });
    }

    public function down(): void
    {
        $prefix = config('items.table_prefix');

        Schema::table($prefix.'order_items', function (Blueprint $table) {
            $table->dropColumn('preorder_quantity');
        });

        Schema::table($prefix.'products', function (Blueprint $table) {
            $table->dropColumn(['incoming_quantity', 'incoming_expected_at']);
        });
    }
};
