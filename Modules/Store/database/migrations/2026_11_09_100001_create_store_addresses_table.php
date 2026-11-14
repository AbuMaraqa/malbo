<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A buyer's saved delivery addresses. At most one is the default: a partial unique index holds it
 * (PostgreSQL), and AddressService keeps it so. A city or district removed from the map leaves the
 * address without it, to be chosen again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('store.table_prefix');
        $geo = config('geolocation.table_prefix');

        Schema::create($prefix.'addresses', function (Blueprint $table) use ($geo) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->string('label', 60)->nullable();
            $table->string('recipient_name', 120);
            $table->string('phone', 40);
            $table->foreignId('city_id')->nullable()->index()->constrained($geo.'cities')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->index()->constrained($geo.'districts')->nullOnDelete();
            $table->string('address_line', 500);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX {$prefix}addresses_one_default ON {$prefix}addresses (user_id) WHERE is_default");
    }

    public function down(): void
    {
        Schema::dropIfExists(config('store.table_prefix').'addresses');
    }
};
