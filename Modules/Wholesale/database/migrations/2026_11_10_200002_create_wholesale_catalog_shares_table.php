<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A merchant's shared PDF catalog links: a random token in a signed URL that works without login
 * until it expires (7 days) or the merchant revokes it. The catalog is drawn when the link is
 * opened, in the language and category chosen when it was made (a deleted category takes its links).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('wholesale.table_prefix');

        Schema::create($prefix.'catalog_shares', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('merchant_id')->index()->constrained($prefix.'merchants')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->foreignId('category_id')->nullable()->index()->constrained(config('items.table_prefix').'categories')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('wholesale.table_prefix').'catalog_shares');
    }
};
