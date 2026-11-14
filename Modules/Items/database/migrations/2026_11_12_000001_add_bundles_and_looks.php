<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8B. Sets and bundle offers are products (`bundle_type`) made of components; they hold no
 * stock of their own. An order sells a bundle as one line per component, each carrying the bundle it
 * came in (`bundle_product_id`, `bundle_group`, the `bundle` snapshot). "Complete the look" links a
 * product to others, in order.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('items.table_prefix');

        Schema::table($prefix.'products', function (Blueprint $table) {
            $table->string('bundle_type', 16)->nullable()->index();
            $table->decimal('bundle_discount_percent', 5, 2)->nullable();
        });

        Schema::create($prefix.'bundle_components', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('bundle_id')->index()->constrained($prefix.'products')->cascadeOnDelete();
            // A component deleted for good leaves its row empty: the bundle cannot be sold without it.
            $table->foreignId('product_id')->nullable()->index()->constrained($prefix.'products')->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create($prefix.'product_looks', function (Blueprint $table) use ($prefix) {
            $table->foreignId('product_id')->constrained($prefix.'products')->cascadeOnDelete();
            $table->foreignId('look_product_id')->index()->constrained($prefix.'products')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->primary(['product_id', 'look_product_id']);
        });

        Schema::table($prefix.'order_items', function (Blueprint $table) use ($prefix) {
            $table->foreignId('bundle_product_id')->nullable()->index()->constrained($prefix.'products')->nullOnDelete();
            $table->string('bundle_group', 36)->nullable()->index();
            $table->jsonb('bundle')->nullable();
        });
    }

    public function down(): void
    {
        $prefix = config('items.table_prefix');

        Schema::table($prefix.'order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bundle_product_id');
            $table->dropColumn(['bundle_group', 'bundle']);
        });

        Schema::dropIfExists($prefix.'product_looks');
        Schema::dropIfExists($prefix.'bundle_components');

        Schema::table($prefix.'products', function (Blueprint $table) {
            $table->dropColumn(['bundle_type', 'bundle_discount_percent']);
        });
    }
};
