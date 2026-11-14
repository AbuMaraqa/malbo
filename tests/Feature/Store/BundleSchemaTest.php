<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\Schema;
use Modules\Store\Settings\StoreAppearanceSettings;
use Tests\DatabaseTestCase;

/**
 * Phase 8B's tables: sets and bundles, complete the look, bundle lines on orders and carts, campaign
 * pages and Instagram posts.
 */
class BundleSchemaTest extends DatabaseTestCase
{
    public function test_the_new_tables_and_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('items_products', ['bundle_type', 'bundle_discount_percent']));
        $this->assertTrue(Schema::hasColumns('items_bundle_components', ['id', 'bundle_id', 'product_id', 'quantity', 'sort_order']));
        $this->assertTrue(Schema::hasColumns('items_product_looks', ['product_id', 'look_product_id', 'sort_order']));
        $this->assertTrue(Schema::hasColumns('items_order_items', ['bundle_product_id', 'bundle_group', 'bundle']));
        $this->assertTrue(Schema::hasColumns('store_cart_items', ['choice_key', 'components']));
        $this->assertTrue(Schema::hasColumns('store_campaign_pages', ['id', 'slug', 'status', 'starts_at', 'ends_at', 'created_by']));
        $this->assertTrue(Schema::hasColumns('store_campaign_page_translations', ['campaign_page_id', 'locale', 'title', 'seo_title', 'seo_description']));
        $this->assertTrue(Schema::hasColumns('store_campaign_blocks', ['id', 'campaign_page_id', 'type', 'sort_order', 'settings', 'content']));
        $this->assertTrue(Schema::hasColumns('store_instagram_posts', ['id', 'permalink', 'caption', 'posted_at', 'sort_order', 'is_active', 'source', 'external_id', 'created_by']));
        $this->assertTrue(Schema::hasColumns('store_instagram_post_product', ['instagram_post_id', 'product_id', 'sort_order']));

        $settings = app(StoreAppearanceSettings::class);
        $this->assertFalse($settings->instagram_enabled);
        $this->assertSame([], $settings->instagram_title);
    }
}
