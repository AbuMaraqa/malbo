<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront carts, held by a long-lived cookie (`token`). Lines hold a product and a quantity,
 * never a price: prices are worked out on every read. A cart that became an order keeps it in
 * `order_id`; the rest, with `last_activity_at`, is what abandoned-cart reminders will read.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('store.table_prefix');

        Schema::create($prefix.'carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('token', 64)->unique();
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->index()->constrained(config('items.table_prefix').'orders')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });

        Schema::create($prefix.'cart_items', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('cart_id')->constrained($prefix.'carts')->cascadeOnDelete();
            $table->foreignId('product_id')->index()->constrained(config('items.table_prefix').'products')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('store.table_prefix');

        Schema::dropIfExists($prefix.'cart_items');
        Schema::dropIfExists($prefix.'carts');
    }
};
