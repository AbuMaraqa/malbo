<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where to deliver an order, picked on the map of the order form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('items.table_prefix').'orders', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(config('items.table_prefix').'orders', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
