<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8B in the store:
 * - a cart line of a set or bundle holds the variant chosen for each component (`components`); the
 *   same bundle with other choices is another line (`choice_key`, empty for an ordinary product);
 * - campaign pages: a slug, a publish state with optional dates, SEO texts per language, and blocks
 *   in order (their texts per language in `content`, their other settings in `settings`);
 * - Instagram posts entered in the admin (a Graph API sync can fill the same table later), each with
 *   the products it shows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('store.table_prefix');

        Schema::table($prefix.'cart_items', function (Blueprint $table) {
            $table->string('choice_key', 64)->default('');
            $table->jsonb('components')->nullable();
        });

        Schema::table($prefix.'cart_items', function (Blueprint $table) use ($prefix) {
            $table->dropUnique($prefix.'cart_items_cart_id_product_id_unique');
            $table->unique(['cart_id', 'product_id', 'choice_key']);
        });

        Schema::create($prefix.'campaign_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create($prefix.'campaign_page_translations', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('campaign_page_id')->constrained($prefix.'campaign_pages')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title', 160);
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->unique(['campaign_page_id', 'locale']);
        });

        Schema::create($prefix.'campaign_blocks', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('campaign_page_id')->index()->constrained($prefix.'campaign_pages')->cascadeOnDelete();
            $table->string('type', 32);
            $table->integer('sort_order')->default(0);
            $table->jsonb('settings')->default('{}');
            $table->jsonb('content')->default('{}');
            $table->timestamps();
        });

        Schema::create($prefix.'instagram_posts', function (Blueprint $table) {
            $table->id();
            $table->string('permalink', 500);
            $table->text('caption')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->string('source', 16)->default('manual');
            $table->string('external_id', 100)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create($prefix.'instagram_post_product', function (Blueprint $table) use ($prefix) {
            $table->foreignId('instagram_post_id')->constrained($prefix.'instagram_posts')->cascadeOnDelete();
            $table->foreignId('product_id')->index()->constrained(config('items.table_prefix').'products')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->primary(['instagram_post_id', 'product_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('store.table_prefix');

        Schema::dropIfExists($prefix.'instagram_post_product');
        Schema::dropIfExists($prefix.'instagram_posts');
        Schema::dropIfExists($prefix.'campaign_blocks');
        Schema::dropIfExists($prefix.'campaign_page_translations');
        Schema::dropIfExists($prefix.'campaign_pages');

        Schema::table($prefix.'cart_items', function (Blueprint $table) use ($prefix) {
            $table->dropUnique($prefix.'cart_items_cart_id_product_id_choice_key_unique');
            $table->unique(['cart_id', 'product_id']);
            $table->dropColumn(['choice_key', 'components']);
        });
    }
};
