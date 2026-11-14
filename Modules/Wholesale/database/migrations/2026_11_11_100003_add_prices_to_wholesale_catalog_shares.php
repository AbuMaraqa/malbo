<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which prices a shared catalog shows: `retail` (the suggested retail price only, the default) or
 * `wholesale` (the merchant's own tier prices, which the merchant chose to share knowingly).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('wholesale.table_prefix').'catalog_shares', function (Blueprint $table) {
            $table->string('prices', 10)->default('retail');
        });
    }

    public function down(): void
    {
        Schema::table(config('wholesale.table_prefix').'catalog_shares', function (Blueprint $table) {
            $table->dropColumn('prices');
        });
    }
};
