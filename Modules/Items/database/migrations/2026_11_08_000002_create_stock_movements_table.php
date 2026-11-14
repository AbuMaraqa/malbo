<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every change to a product's stock: a sale, a cancellation or return, an adjustment, incoming
 * goods. `quantity` is signed (negative takes stock away); `balance_after` is the stock after it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('items.table_prefix');

        Schema::create($prefix.'stock_movements', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('product_id')->index()->constrained($prefix.'products')->cascadeOnDelete();
            $table->string('type', 16);
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->foreignId('order_id')->nullable()->index()->constrained($prefix.'orders')->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix').'stock_movements');
    }
};
