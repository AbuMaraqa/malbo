<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a product costs wholesale beyond its `wholesale_price`: a price per tier for a sellable item
 * (a variant, or a product without variants), and quantity breaks per product (a parent, or a
 * product without variants). And whether a product is sold by the piece or only in size runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('wholesale.table_prefix');
        $products = config('items.table_prefix').'products';

        Schema::create($prefix.'tier_prices', function (Blueprint $table) use ($prefix, $products) {
            $table->id();
            $table->foreignId('product_id')->constrained($products)->cascadeOnDelete();
            $table->foreignId('tier_id')->index()->constrained($prefix.'tiers')->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->timestamps();
            $table->unique(['product_id', 'tier_id']);
        });

        Schema::create($prefix.'quantity_breaks', function (Blueprint $table) use ($products) {
            $table->id();
            $table->foreignId('product_id')->constrained($products)->cascadeOnDelete();
            $table->unsignedInteger('min_quantity');
            $table->decimal('discount_percent', 5, 2);
            $table->timestamps();
            $table->unique(['product_id', 'min_quantity']);
        });

        Schema::table($products, function (Blueprint $table) {
            $table->boolean('wholesale_by_piece')->default(true);
        });
    }

    public function down(): void
    {
        $prefix = config('wholesale.table_prefix');

        Schema::table(config('items.table_prefix').'products', function (Blueprint $table) {
            $table->dropColumn('wholesale_by_piece');
        });

        Schema::dropIfExists($prefix.'quantity_breaks');
        Schema::dropIfExists($prefix.'tier_prices');
    }
};
