<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8A (marketing), additive:
 * - carts keep the contact a buyer agreed to be reminded at (typed at checkout), when they agreed,
 *   and the language they shopped in;
 * - one row per cart reminder sent (or failed): unique per cart and sequence, so two runs never
 *   send the same reminder;
 * - contacts that asked for no more marketing messages (normalised email or phone digits);
 * - "notify me" requests: one waiting request per variant and contact (partial unique indexes);
 * - product reviews, one per product and account.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('store.table_prefix');
        $products = config('items.table_prefix').'products';

        Schema::table($prefix.'carts', function (Blueprint $table) {
            $table->string('contact_email', 160)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->timestamp('contact_consent_at')->nullable();
            $table->string('locale', 5)->nullable();
        });

        Schema::create($prefix.'cart_reminders', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('cart_id')->constrained($prefix.'carts')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('channel', 10);
            $table->string('recipient', 160);
            $table->string('status', 10)->default('sending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['cart_id', 'sequence']);
        });

        Schema::create($prefix.'marketing_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 10);
            $table->string('value', 160);
            $table->timestamps();
            $table->unique(['channel', 'value']);
        });

        Schema::create($prefix.'stock_alerts', function (Blueprint $table) use ($products) {
            $table->id();
            $table->foreignId('product_id')->index()->constrained($products)->cascadeOnDelete();
            $table->string('email', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('locale', 5);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX {$prefix}stock_alerts_waiting_email ON {$prefix}stock_alerts (product_id, email) WHERE notified_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefix}stock_alerts_waiting_phone ON {$prefix}stock_alerts (product_id, phone) WHERE notified_at IS NULL");

        Schema::create($prefix.'reviews', function (Blueprint $table) use ($products) {
            $table->id();
            $table->foreignId('product_id')->constrained($products)->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->index()->constrained(config('items.table_prefix').'orders')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('body');
            $table->string('status', 10)->default('pending')->index();
            $table->text('staff_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'user_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('store.table_prefix');

        Schema::dropIfExists($prefix.'reviews');
        Schema::dropIfExists($prefix.'stock_alerts');
        Schema::dropIfExists($prefix.'marketing_opt_outs');
        Schema::dropIfExists($prefix.'cart_reminders');

        Schema::table($prefix.'carts', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'contact_phone', 'contact_consent_at', 'locale']);
        });
    }
};
