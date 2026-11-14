<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 (reports), additive: the date columns the reports range over, and the completed-request
 * lookup of the returns. Indexes only; no data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('items.table_prefix').'orders', function (Blueprint $table) {
            $table->index(['created_at', 'id'], 'items_orders_report_created_idx');
        });

        Schema::table(config('items.table_prefix').'return_requests', function (Blueprint $table) {
            $table->index(['status', 'type'], 'items_return_requests_report_status_type_idx');
        });

        Schema::table(config('store.table_prefix').'carts', function (Blueprint $table) {
            $table->index('created_at', 'store_carts_report_created_idx');
        });

        Schema::table(config('store.table_prefix').'cart_reminders', function (Blueprint $table) {
            $table->index(['status', 'sent_at'], 'store_cart_reminders_report_sent_idx');
        });

        Schema::table(config('wholesale.table_prefix').'carts', function (Blueprint $table) {
            $table->index('created_at', 'wholesale_carts_report_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table(config('wholesale.table_prefix').'carts', fn (Blueprint $table) => $table->dropIndex('wholesale_carts_report_created_idx'));
        Schema::table(config('store.table_prefix').'cart_reminders', fn (Blueprint $table) => $table->dropIndex('store_cart_reminders_report_sent_idx'));
        Schema::table(config('store.table_prefix').'carts', fn (Blueprint $table) => $table->dropIndex('store_carts_report_created_idx'));
        Schema::table(config('items.table_prefix').'return_requests', fn (Blueprint $table) => $table->dropIndex('items_return_requests_report_status_type_idx'));
        Schema::table(config('items.table_prefix').'orders', fn (Blueprint $table) => $table->dropIndex('items_orders_report_created_idx'));
    }
};
