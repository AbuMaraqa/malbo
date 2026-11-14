<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping zones: a group of cities sharing a delivery fee, an expected delivery time and a
 * free-delivery threshold. A city belongs to at most one active zone (checked on save).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('store.table_prefix');

        Schema::create($prefix.'shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->decimal('fee', 10, 2)->default(0);
            $table->unsignedSmallInteger('eta_min_days')->default(1);
            $table->unsignedSmallInteger('eta_max_days')->default(3);
            $table->decimal('free_shipping_threshold', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create($prefix.'shipping_zone_translations', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('shipping_zone_id')->index()->constrained($prefix.'shipping_zones')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->unique(['shipping_zone_id', 'locale']);
        });

        Schema::create($prefix.'shipping_zone_city', function (Blueprint $table) use ($prefix) {
            $table->foreignId('shipping_zone_id')->constrained($prefix.'shipping_zones')->cascadeOnDelete();
            $table->foreignId('city_id')->index()->constrained(config('geolocation.table_prefix').'cities')->cascadeOnDelete();
            $table->primary(['shipping_zone_id', 'city_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('store.table_prefix');

        Schema::dropIfExists($prefix.'shipping_zone_city');
        Schema::dropIfExists($prefix.'shipping_zone_translations');
        Schema::dropIfExists($prefix.'shipping_zones');
    }
};
