<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront checkout: the coupon an order used, guest orders (no staff member creates them), and
 * a row for every status an order reaches, with its date (the tracking timeline).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('items.table_prefix');

        Schema::table($prefix.'orders', function (Blueprint $table) {
            $table->string('coupon_code', 50)->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });

        Schema::create($prefix.'order_status_history', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('order_id')->index()->constrained($prefix.'orders')->cascadeOnDelete();
            $table->integer('status');
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('items.table_prefix');

        Schema::dropIfExists($prefix.'order_status_history');

        Schema::table($prefix.'orders', function (Blueprint $table) {
            $table->dropColumn('coupon_code');
        });
    }
};
