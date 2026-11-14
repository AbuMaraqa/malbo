<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A buyer's wishlist: products (a parent or a single product, never a variant), once each.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('store.table_prefix');

        Schema::create($prefix.'wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->index()->constrained(config('items.table_prefix').'products')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('store.table_prefix').'wishlist_items');
    }
};
