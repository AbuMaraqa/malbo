<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The wholesale cart: a merchant's own, never the storefront's. Lines hold a sellable product and a
 * quantity, never a price. One open cart per user (a partial unique index); an ordered cart keeps
 * its order.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('wholesale.table_prefix');

        Schema::create($prefix.'carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->index()->constrained(config('items.table_prefix').'orders')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX {$prefix}carts_one_open ON {$prefix}carts (user_id) WHERE order_id IS NULL");

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
        $prefix = config('wholesale.table_prefix');

        Schema::dropIfExists($prefix.'cart_items');
        Schema::dropIfExists($prefix.'carts');
    }
};
