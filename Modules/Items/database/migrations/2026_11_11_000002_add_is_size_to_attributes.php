<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The attribute staff mark as the size: product cards list its values and the product page puts the
 * size guide by it. At most one, kept by a partial unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('items.table_prefix');

        Schema::table($prefix.'attributes', function (Blueprint $table) {
            $table->boolean('is_size')->default(false);
        });

        DB::statement("CREATE UNIQUE INDEX {$prefix}attributes_one_size ON {$prefix}attributes (is_size) WHERE is_size");
    }

    public function down(): void
    {
        $prefix = config('items.table_prefix');

        DB::statement("DROP INDEX IF EXISTS {$prefix}attributes_one_size");

        Schema::table($prefix.'attributes', function (Blueprint $table) {
            $table->dropColumn('is_size');
        });
    }
};
