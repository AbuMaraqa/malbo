<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A merchant's ledger is a financial record: deleting the merchant's user, or the merchant, is
 * refused instead of taking the account with it (both foreign keys were cascades). NO ACTION, not
 * RESTRICT: for these immediate constraints they refuse alike, and NO ACTION raises the SQLSTATE
 * 23503 that UserService turns into "linked to other records".
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('wholesale.table_prefix');

        Schema::table($prefix.'merchants', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->noActionOnDelete();
        });

        Schema::table($prefix.'ledger_entries', function (Blueprint $table) use ($prefix) {
            $table->dropForeign(['merchant_id']);
            $table->foreign('merchant_id')->references('id')->on($prefix.'merchants')->noActionOnDelete();
        });
    }

    public function down(): void
    {
        $prefix = config('wholesale.table_prefix');

        Schema::table($prefix.'ledger_entries', function (Blueprint $table) use ($prefix) {
            $table->dropForeign(['merchant_id']);
            $table->foreign('merchant_id')->references('id')->on($prefix.'merchants')->cascadeOnDelete();
        });

        Schema::table($prefix.'merchants', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
