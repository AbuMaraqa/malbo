<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchants: the wholesale profile of a user with the Merchant role (one each), what they told us
 * when they applied, their status, tier, sales rep and minimum-order override; and every status
 * change with its note and author.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('wholesale.table_prefix');

        Schema::create($prefix.'merchants', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('shop_name', 160);
            $table->string('owner_name', 120);
            $table->foreignId('city_id')->nullable()->index()->constrained(config('geolocation.table_prefix').'cities')->nullOnDelete();
            $table->string('phone', 40);
            $table->string('email', 160);
            $table->string('commercial_register', 60);
            $table->string('register_document_path')->nullable();
            $table->string('register_document_name')->nullable();
            $table->string('shop_address', 500)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('tier_id')->nullable()->index()->constrained($prefix.'tiers')->nullOnDelete();
            $table->foreignId('sales_rep_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->decimal('minimum_order_value', 10, 2)->nullable();
            $table->unsignedInteger('minimum_order_pieces')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create($prefix.'merchant_status_changes', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('merchant_id')->index()->constrained($prefix.'merchants')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('wholesale.table_prefix');

        Schema::dropIfExists($prefix.'merchant_status_changes');
        Schema::dropIfExists($prefix.'merchants');
    }
};
