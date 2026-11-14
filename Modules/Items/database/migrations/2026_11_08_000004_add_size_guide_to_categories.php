<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A category's size guide, translated rich text (sanitized by RichText). A product page shows the
 * guide of the product's first category that has one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('items.table_prefix').'category_translations', function (Blueprint $table) {
            $table->text('size_guide')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(config('items.table_prefix').'category_translations', function (Blueprint $table) {
            $table->dropColumn('size_guide');
        });
    }
};
