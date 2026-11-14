<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wholesale price tiers: a merchant's level, as a discount off the base wholesale price. At most
 * one tier is the default (a partial unique index): merchants without a tier get it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('wholesale.table_prefix');

        Schema::create($prefix.'tiers', function (Blueprint $table) {
            $table->id();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX {$prefix}tiers_one_default ON {$prefix}tiers (is_default) WHERE is_default");

        Schema::create($prefix.'tier_translations', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('tier_id')->constrained($prefix.'tiers')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->unique(['tier_id', 'locale']);
        });
    }

    public function down(): void
    {
        $prefix = config('wholesale.table_prefix');

        Schema::dropIfExists($prefix.'tier_translations');
        Schema::dropIfExists($prefix.'tiers');
    }
};
