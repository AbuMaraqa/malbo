<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collections: groups of products the admin curates ("Summer 2026", "Linen"), each with its own
 * storefront page. A product can be in many collections, in the order the admin picked.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('items.table_prefix');

        Schema::create($prefix.'collections', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create($prefix.'collection_translations', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('collection_id')->index()->constrained($prefix.'collections')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unique(['collection_id', 'locale']);
        });

        Schema::create($prefix.'collection_product', function (Blueprint $table) use ($prefix) {
            $table->foreignId('collection_id')->constrained($prefix.'collections')->cascadeOnDelete();
            $table->foreignId('product_id')->index()->constrained($prefix.'products')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->primary(['collection_id', 'product_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('items.table_prefix');

        Schema::dropIfExists($prefix.'collection_product');
        Schema::dropIfExists($prefix.'collection_translations');
        Schema::dropIfExists($prefix.'collections');
    }
};
