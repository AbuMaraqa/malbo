<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * products.brand_id was `cascade`: deleting a brand in the admin deleted all its products, for good.
 * A product without a brand is valid, so the column is emptied instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('items.table_prefix').'products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->foreign('brand_id')->references('id')->on(config('items.table_prefix').'brands')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(config('items.table_prefix').'products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->foreign('brand_id')->references('id')->on(config('items.table_prefix').'brands')->cascadeOnDelete();
        });
    }
};
