<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Items\Enums\AttributeType;

/**
 * Search and filters (phase 7):
 * - `is_filterable` on attributes: the shop offers the attribute as a filter (colors are, at first);
 * - `sales_count` on products: units sold, for the "best selling" sort (written by SalesCounter only);
 * - the indexes the filters read: a value's variants, a parent's variants by state, the best sellers;
 * - trigram indexes for `ILIKE '%term%'` on SKUs and tag names (pg_trgm is created here when the
 *   database has not got it yet: the general trigram migration runs after the modules').
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('items.table_prefix');

        Schema::table($prefix.'attributes', function (Blueprint $table) {
            $table->boolean('is_filterable')->default(false);
        });

        DB::table($prefix.'attributes')->where('type', AttributeType::COLOR->value)->update(['is_filterable' => true]);

        Schema::table($prefix.'products', function (Blueprint $table) use ($prefix) {
            $table->unsignedInteger('sales_count')->default(0);
            $table->index('sales_count', $prefix.'products_sales_count_idx');
            $table->index(['parent_id', 'status', 'is_active'], $prefix.'products_parent_status_active_idx');
        });

        Schema::table($prefix.'product_attribute_value', function (Blueprint $table) use ($prefix) {
            $table->index(['attribute_id', 'product_id'], $prefix.'product_attribute_value_value_product_idx');
        });

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement("CREATE INDEX IF NOT EXISTS {$prefix}products_sku_trgm ON {$prefix}products USING gin (sku gin_trgm_ops)");
        DB::statement("CREATE INDEX IF NOT EXISTS {$prefix}tag_translations_name_trgm ON {$prefix}tag_translations USING gin (name gin_trgm_ops)");
    }

    public function down(): void
    {
        $prefix = config('items.table_prefix');

        DB::statement("DROP INDEX IF EXISTS {$prefix}tag_translations_name_trgm");
        DB::statement("DROP INDEX IF EXISTS {$prefix}products_sku_trgm");

        Schema::table($prefix.'product_attribute_value', function (Blueprint $table) use ($prefix) {
            $table->dropIndex($prefix.'product_attribute_value_value_product_idx');
        });

        Schema::table($prefix.'products', function (Blueprint $table) use ($prefix) {
            $table->dropIndex($prefix.'products_parent_status_active_idx');
            $table->dropIndex($prefix.'products_sales_count_idx');
            $table->dropColumn('sales_count');
        });

        Schema::table($prefix.'attributes', function (Blueprint $table) {
            $table->dropColumn('is_filterable');
        });
    }
};
