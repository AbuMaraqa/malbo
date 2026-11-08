<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('items.table_prefix') . 'products', function (Blueprint $table) {
            $table->index(['updated_at', 'id'], 'items_products_updated_id_idx');
            $table->index(['is_active', 'status'], 'items_products_active_status_idx');
            $table->index(['parent_id', 'is_active'], 'items_products_parent_active_idx');
        });

        Schema::table(config('items.table_prefix') . 'categories', function (Blueprint $table) {
            $table->index(['updated_at', 'id'], 'items_categories_updated_id_idx');
        });

        Schema::table(config('items.table_prefix') . 'addons', function (Blueprint $table) {
            $table->index(['updated_at', 'id'], 'items_addons_updated_id_idx');
        });

        Schema::table(config('items.table_prefix') . 'addon_options', function (Blueprint $table) {
            $table->index(['updated_at', 'id'], 'items_addon_options_updated_id_idx');
        });

        Schema::table(config('items.table_prefix') . 'offers', function (Blueprint $table) {
            $table->index(['updated_at', 'id'], 'items_offers_updated_id_idx');
            $table->index(['is_active', 'branch_id', 'start_date', 'end_date'], 'items_offers_active_branch_dates_idx');
        });

        Schema::table(config('items.table_prefix') . 'orders', function (Blueprint $table) {
            $table->index(['updated_at', 'id'], 'items_orders_updated_id_idx');
            $table->index(['status', 'created_at'], 'items_orders_status_created_idx');
            $table->index(['payment_status', 'created_at'], 'items_orders_payment_status_created_idx');
            $table->index(['user_id', 'created_at'], 'items_orders_user_created_idx');
        });

        Schema::table(config('items.table_prefix') . 'branch_prices', function (Blueprint $table) {
            $table->index(['product_id', 'branch_id'], 'items_branch_prices_product_branch_idx');
        });

        Schema::table(config('items.table_prefix') . 'addon_product_option', function (Blueprint $table) {
            $table->index('addon_option_id', 'items_addon_product_option_option_idx');
        });
    }

    public function down(): void
    {
        Schema::table(config('items.table_prefix') . 'addon_product_option', function (Blueprint $table) {
            $table->dropIndex('items_addon_product_option_option_idx');
        });

        Schema::table(config('items.table_prefix') . 'orders', function (Blueprint $table) {
            $table->dropIndex('items_orders_user_created_idx');
            $table->dropIndex('items_orders_payment_status_created_idx');
            $table->dropIndex('items_orders_status_created_idx');
            $table->dropIndex('items_orders_updated_id_idx');
        });

        Schema::table(config('items.table_prefix') . 'branch_prices', function (Blueprint $table) {
            $table->dropIndex('items_branch_prices_product_branch_idx');
        });

        Schema::table(config('items.table_prefix') . 'offers', function (Blueprint $table) {
            $table->dropIndex('items_offers_active_branch_dates_idx');
            $table->dropIndex('items_offers_updated_id_idx');
        });

        Schema::table(config('items.table_prefix') . 'addon_options', function (Blueprint $table) {
            $table->dropIndex('items_addon_options_updated_id_idx');
        });

        Schema::table(config('items.table_prefix') . 'addons', function (Blueprint $table) {
            $table->dropIndex('items_addons_updated_id_idx');
        });

        Schema::table(config('items.table_prefix') . 'categories', function (Blueprint $table) {
            $table->dropIndex('items_categories_updated_id_idx');
        });

        Schema::table(config('items.table_prefix') . 'products', function (Blueprint $table) {
            $table->dropIndex('items_products_parent_active_idx');
            $table->dropIndex('items_products_active_status_idx');
            $table->dropIndex('items_products_updated_id_idx');
        });
    }
};
