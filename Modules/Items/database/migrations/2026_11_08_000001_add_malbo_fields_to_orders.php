<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order's channel (store or wholesale), who it is for, staff-only notes, and a snapshot of what
 * each line sold: its SKU, its name in every language and its options (color, size...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('items.table_prefix').'orders', function (Blueprint $table) {
            $table->string('channel', 8)->default('b2c');
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->text('internal_notes')->nullable();
        });

        Schema::table(config('items.table_prefix').'order_items', function (Blueprint $table) {
            $table->string('sku')->nullable();
            $table->jsonb('product_name')->nullable();
            $table->jsonb('options')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(config('items.table_prefix').'order_items', function (Blueprint $table) {
            $table->dropColumn(['sku', 'product_name', 'options']);
        });

        Schema::table(config('items.table_prefix').'orders', function (Blueprint $table) {
            $table->dropColumn(['channel', 'customer_name', 'customer_email', 'internal_notes']);
        });
    }
};
