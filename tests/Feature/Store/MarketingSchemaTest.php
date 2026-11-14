<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Store\Settings\MarketingSettings;
use Tests\DatabaseTestCase;

/**
 * Phase 8A's tables, the cart's new columns and the marketing settings.
 */
class MarketingSchemaTest extends DatabaseTestCase
{
    public function test_the_new_tables_and_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('store_carts', ['contact_email', 'contact_phone', 'contact_consent_at', 'locale']));
        $this->assertTrue(Schema::hasColumns('store_cart_reminders', ['cart_id', 'sequence', 'channel', 'recipient', 'status', 'sent_at']));
        $this->assertTrue(Schema::hasColumns('store_marketing_opt_outs', ['channel', 'value']));
        $this->assertTrue(Schema::hasColumns('store_stock_alerts', ['product_id', 'email', 'phone', 'locale', 'notified_at', 'created_at']));
        $this->assertTrue(Schema::hasColumns('store_reviews', ['product_id', 'user_id', 'order_id', 'rating', 'title', 'body', 'status', 'staff_reply', 'replied_at']));

        $definitions = DB::table('pg_indexes')->where('schemaname', 'public')->pluck('indexdef')->all();
        $has = fn (string $pattern) => collect($definitions)->contains(fn (string $definition) => (bool) preg_match($pattern, $definition));

        $this->assertTrue($has('/UNIQUE INDEX \S+ ON public\.store_cart_reminders USING btree \(cart_id, sequence\)/'));
        $this->assertTrue($has('/UNIQUE INDEX \S+ ON public\.store_marketing_opt_outs USING btree \(channel, value\)/'));
        $this->assertTrue($has('/UNIQUE INDEX store_stock_alerts_waiting_email ON public\.store_stock_alerts USING btree \(product_id, email\) WHERE \(notified_at IS NULL\)/'));
        $this->assertTrue($has('/UNIQUE INDEX store_stock_alerts_waiting_phone ON public\.store_stock_alerts USING btree \(product_id, phone\) WHERE \(notified_at IS NULL\)/'));
        $this->assertTrue($has('/UNIQUE INDEX \S+ ON public\.store_reviews USING btree \(product_id, user_id\)/'));
    }

    public function test_the_marketing_settings_and_their_defaults(): void
    {
        $settings = app(MarketingSettings::class);

        $this->assertFalse($settings->pixel_enabled);
        $this->assertFalse($settings->capi_enabled);
        $this->assertNull($settings->meta_pixel_id);
        $this->assertNull($settings->meta_capi_token);
        $this->assertNull($settings->feed_token);
        $this->assertFalse($settings->cart_reminders_enabled);
        $this->assertSame(60, $settings->abandoned_after_minutes);
        $this->assertSame([60, 1440], $settings->reminder_delays);
    }

    public function test_the_capi_token_is_encrypted_at_rest(): void
    {
        $settings = app(MarketingSettings::class);
        $settings->meta_capi_token = 'EAAB-secret-token';
        $settings->save();

        $stored = (string) DB::table('settings')->where('group', 'store_marketing')->where('name', 'meta_capi_token')->value('payload');

        $this->assertStringNotContainsString('EAAB-secret-token', $stored);
        $this->assertSame('EAAB-secret-token', app(MarketingSettings::class)->refresh()->meta_capi_token);
    }
}
