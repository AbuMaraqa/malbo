<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Category;
use Modules\Wholesale\Entities\CatalogShare;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\QuantityBreak;
use Modules\Wholesale\Entities\TierPrice;
use Modules\Wholesale\Enums\MerchantStatus;
use Modules\Wholesale\Services\CatalogPdfService;
use Modules\Wholesale\Services\MerchantService;

/**
 * The PDF catalog an approved merchant shares on WhatsApp: their own tier prices with the quantity
 * breaks and the suggested retail price, never another tier's price or a cost price. Downloaded, or
 * shared as a signed link that works without login for 7 days and can be revoked.
 */
class CatalogPdfTest extends WholesaleTestCase
{
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->merchant(attributes: ['tier_id' => $this->tier('ذهبي', 10)->id]);
    }

    public function test_the_document_holds_the_merchants_tier_prices_breaks_and_retail_only(): void
    {
        [$shirt, $variants] = $this->shirt(['cost_price' => 12.34]);
        $silver = $this->tier('فضي', 0);
        TierPrice::query()->create(['product_id' => $variants['black-S']->id, 'tier_id' => $silver->id, 'price' => 33.33]);
        QuantityBreak::query()->create(['product_id' => $shirt->id, 'min_quantity' => 12, 'discount_percent' => 5]);

        $document = app(CatalogPdfService::class)->document($this->merchant, null, 'ar', CatalogPdfService::WHOLESALE);

        $this->assertSame('rtl', $document['dir']);
        $this->assertSame('محل الأناقة', $document['shop']);
        $product = collect($document['products'])->firstWhere('name', 'قميص جملة');
        $this->assertSame('45.00 ₪', $product['price_label']); // 50 - 10 %
        $this->assertSame('100.00 ₪', $product['retail_label']);
        $this->assertSame(['أسود', 'أبيض'], $product['colors']);
        $this->assertSame(['S', 'M', 'L'], $product['sizes']);
        $this->assertCount(1, $product['breaks']);

        $json = json_encode($document, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('33.33', $json);
        $this->assertStringNotContainsString('12.34', $json);
        $this->assertStringNotContainsString('فضي', $json);
    }

    public function test_retail_is_the_default_and_holds_no_wholesale_price(): void
    {
        [$shirt, $variants] = $this->shirt();
        TierPrice::query()->create(['product_id' => $variants['black-S']->id, 'tier_id' => $this->merchant->tier_id, 'price' => 41.17]);
        QuantityBreak::query()->create(['product_id' => $shirt->id, 'min_quantity' => 12, 'discount_percent' => 5]);

        $document = app(CatalogPdfService::class)->document($this->merchant, null, 'ar');

        $product = collect($document['products'])->firstWhere('name', 'قميص جملة');
        $this->assertSame(CatalogPdfService::RETAIL, $document['prices']);
        $this->assertSame('100.00 ₪', $product['retail_label']);
        $this->assertNull($product['price_label']);
        $this->assertSame([], $product['breaks']);
        $json = json_encode($document, JSON_UNESCAPED_UNICODE);
        foreach (['45.00', '41.17', '50.00'] as $value) {
            $this->assertStringNotContainsString($value, $json, $value);
        }
    }

    public function test_a_default_share_shows_retail_and_a_wholesale_share_only_this_tier(): void
    {
        [, $variants] = $this->shirt();
        $silver = $this->tier('فضي', 0);
        TierPrice::query()->create(['product_id' => $variants['black-S']->id, 'tier_id' => $silver->id, 'price' => 33.33]);

        $this->actingAs($this->merchant->user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), [])->assertSessionHasNoErrors();
        $this->actingAs($this->merchant->user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), ['prices' => 'wholesale'])->assertSessionHasNoErrors();
        [$retail, $wholesale] = CatalogShare::query()->orderBy('id')->get()->all();
        $this->assertSame('retail', $retail->prices);
        $this->assertSame('wholesale', $wholesale->prices);

        $retailJson = json_encode(app(CatalogPdfService::class)->forShare($retail), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('45.00', $retailJson);
        $this->assertStringNotContainsString('33.33', $retailJson);

        $wholesaleJson = json_encode(app(CatalogPdfService::class)->forShare($wholesale), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('45.00', $wholesaleJson);
        $this->assertStringNotContainsString('33.33', $wholesaleJson);

        // The page says which links show purchase prices.
        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/catalog-pdf'))->assertInertia(fn (Assert $page) => $page
            ->where('shares.0.prices', 'wholesale')
            ->where('shares.1.prices', 'retail')
            ->etc());
        $this->actingAs($this->merchant->user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), ['prices' => 'cost'])->assertSessionHasErrors('prices');
    }

    public function test_a_product_image_is_a_small_thumbnail(): void
    {
        \Illuminate\Support\Facades\Storage::fake('items');
        $belt = $this->single();
        $belt->addMedia(\Illuminate\Http\UploadedFile::fake()->image('belt.jpg', 2400, 1800))->toMediaCollection('product', 'items');

        $image = collect(app(CatalogPdfService::class)->document($this->merchant, null, 'ar')['products'])->firstWhere('name', 'حزام')['image'];

        $this->assertNotNull($image);
        $this->assertFileExists($image);
        $this->assertLessThanOrEqual(CatalogPdfService::MAX_IMAGE_BYTES, filesize($image));
        $this->assertLessThanOrEqual(CatalogPdfService::THUMB_WIDTH, getimagesize($image)[0]);
    }

    public function test_images_stop_at_the_budget(): void
    {
        \Illuminate\Support\Facades\Storage::fake('items');
        foreach (['حزام أ', 'حزام ب', 'حزام ج'] as $name) {
            $this->single(['name' => $name])->addMedia(\Illuminate\Http\UploadedFile::fake()->image('b.jpg', 800, 600))->toMediaCollection('product', 'items');
        }

        // A budget a little over one thumbnail: the first image fits, the rest are left out.
        config(['wholesale.catalog_pdf.image_budget' => 1]);
        $this->assertSame(0, collect(app(CatalogPdfService::class)->document($this->merchant, null, 'ar')['products'])->whereNotNull('image')->count());

        config(['wholesale.catalog_pdf.image_budget' => null]); // the default: 25 MB
        $one = filesize(collect(app(CatalogPdfService::class)->document($this->merchant, null, 'ar', limit: 1)['products'])->first()['image']);
        config(['wholesale.catalog_pdf.image_budget' => $one + 10]);

        $this->assertSame(1, collect(app(CatalogPdfService::class)->document($this->merchant, null, 'ar')['products'])->whereNotNull('image')->count());
    }

    public function test_a_shared_pdf_is_cached_and_limited_per_link(): void
    {
        $this->single();
        $this->actingAs($this->merchant->user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), []);
        $share = CatalogShare::query()->sole();
        $url = $share->url();
        auth()->logout();

        $this->get($url)->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has(CatalogPdfService::shareCacheKey($share)));

        foreach (range(2, 30) as $ignored) {
            $this->get($url)->assertOk();
        }
        $this->get($url)->assertStatus(429);
    }

    public function test_a_download_is_cached(): void
    {
        $this->single();

        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/catalog-pdf/download?prices=wholesale'))->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Cache::has(CatalogPdfService::downloadCacheKey($this->merchant, null, 'ar', CatalogPdfService::WHOLESALE)));
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has(CatalogPdfService::downloadCacheKey($this->merchant, null, 'ar', CatalogPdfService::RETAIL)));
    }

    public function test_english_is_left_to_right(): void
    {
        $this->single();

        $this->assertSame('ltr', app(CatalogPdfService::class)->document($this->merchant, null, 'en')['dir']);
        $this->assertSame('rtl', app(CatalogPdfService::class)->document($this->merchant, null, 'he')['dir']);
    }

    public function test_the_category_filter(): void
    {
        $this->shirt();
        $belt = $this->single();
        $category = Category::factory()->create(['name' => 'أحزمة', 'slug' => 'belts', 'status' => 1]);
        $belt->categories()->attach($category->id);

        $document = app(CatalogPdfService::class)->document($this->merchant, 'belts', 'ar');

        $this->assertSame(['حزام'], array_column($document['products'], 'name'));
        $this->assertSame('أحزمة', $document['category']);
    }

    public function test_it_is_capped_at_200_products(): void
    {
        $this->single();
        $this->single(['name' => 'حزام 2']);
        $this->single(['name' => 'حزام 3']);

        $document = app(CatalogPdfService::class)->document($this->merchant, null, 'ar', limit: 2);

        $this->assertCount(2, $document['products']);
        $this->assertTrue($document['truncated']);
        $this->assertSame(200, CatalogPdfService::LIMIT);
    }

    public function test_download_returns_a_pdf(): void
    {
        $this->single();

        $response = $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/catalog-pdf/download'))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_generation_is_rate_limited(): void
    {
        // Downloads and links count together: 10 an hour.
        foreach (range(1, 10) as $ignored) {
            $this->actingAs($this->merchant->user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), [])->assertSessionHasNoErrors();
        }

        $this->actingAs($this->merchant->user)->from($this->siteUrl('/ar/wholesale/catalog-pdf'))
            ->get($this->siteUrl('/ar/wholesale/catalog-pdf/download'))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->merchant->user)->from($this->siteUrl('/ar/wholesale/catalog-pdf'))
            ->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), [])->assertSessionHas('error');
        $this->assertSame(10, CatalogShare::query()->count());

        // Another merchant has their own count.
        $this->flushSession();
        $this->actingAs($this->merchant()->user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), [])->assertSessionMissing('error');
        $this->assertSame(11, CatalogShare::query()->count());
    }

    public function test_a_share_link_opens_without_login_for_7_days(): void
    {
        $this->single();

        $this->actingAs($this->merchant->user)->from($this->siteUrl('/ar/wholesale/catalog-pdf'))
            ->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), ['category' => null])->assertRedirect()->assertSessionHasNoErrors();

        $share = CatalogShare::query()->sole();
        $this->assertSame($this->merchant->id, $share->merchant_id);
        $this->assertSame('ar', $share->locale);
        $this->assertTrue($share->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));

        $url = $this->shareUrl();
        auth()->logout();
        $this->flushSession();

        $response = $this->get($url)->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));

        Carbon::setTestNow(now()->addDays(7)->addMinute());
        $this->get($url)->assertForbidden(); // the signature expired
        Carbon::setTestNow();
    }

    public function test_a_revoked_or_tampered_link_is_refused(): void
    {
        $this->single();
        $this->actingAs($this->merchant->user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), []);
        $share = CatalogShare::query()->sole();
        $url = $this->shareUrl();

        $this->get($url.'x')->assertForbidden();
        $this->get(str_replace($share->token, str_repeat('a', strlen($share->token)), $url))->assertForbidden();

        $this->actingAs($this->merchant->user)->delete($this->siteUrl('/ar/wholesale/catalog-pdf/shares/'.$share->id))->assertRedirect();
        $this->assertNotNull($share->fresh()->revoked_at);

        auth()->logout();
        $this->get($url)->assertNotFound();
    }

    public function test_a_suspended_merchants_link_stops_working(): void
    {
        $this->single();
        $this->actingAs($this->merchant->user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), []);
        $url = $this->shareUrl();

        app(MerchantService::class)->changeStatus($this->merchant, MerchantStatus::SUSPENDED, 'متأخر', $this->staff([])->id);
        auth()->logout();

        $this->get($url)->assertNotFound();
    }

    public function test_only_the_owner_revokes(): void
    {
        $this->actingAs($this->merchant->user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), []);
        $share = CatalogShare::query()->sole();
        $other = $this->merchant();
        $this->flushSession();

        $this->actingAs($other->user)->delete($this->siteUrl('/ar/wholesale/catalog-pdf/shares/'.$share->id))->assertNotFound();
        $this->assertNull($share->fresh()->revoked_at);
    }

    public function test_the_page_lists_shares_with_a_whatsapp_link(): void
    {
        $category = Category::factory()->create(['name' => 'أحزمة', 'slug' => 'belts', 'status' => 1]);
        $this->actingAs($this->merchant->user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), ['category' => 'belts']);
        $this->flushSession();
        $this->actingAs($this->merchant()->user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), []); // someone else's
        $this->assertSame(2, CatalogShare::query()->count());
        $this->flushSession();

        $this->actingAs($this->merchant->user)->get($this->siteUrl('/ar/wholesale/catalog-pdf'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wholesale/Portal/CatalogPdf')
            ->has('shares', 1)
            ->where('shares.0.category', 'أحزمة')
            ->where('shares.0.url', $this->shareUrl())
            ->where('shares.0.whatsapp_url', fn (string $whatsapp) => str_starts_with($whatsapp, 'https://wa.me/?text=') && str_contains(urldecode($whatsapp), $this->shareUrl()))
            ->where('maxProducts', 200)
            ->has('categories')
            ->etc());
        $this->assertSame($category->id, CatalogShare::query()->where('merchant_id', $this->merchant->id)->value('category_id'));
    }

    public function test_the_portal_pages_need_an_approved_merchant(): void
    {
        $pending = $this->merchant(MerchantStatus::PENDING);

        $this->actingAs($pending->user)->get($this->siteUrl('/ar/wholesale/catalog-pdf/download'))->assertRedirect(route('store.wholesale.status'));
        $this->actingAs($pending->user)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), [])->assertRedirect(route('store.wholesale.status'));
        $this->assertSame(0, CatalogShare::query()->count());
    }

    private function shareUrl(): string
    {
        return CatalogShare::query()->where('merchant_id', $this->merchant->id)->sole()->url();
    }
}
