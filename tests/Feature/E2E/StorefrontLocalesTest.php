<?php

namespace Tests\Feature\E2E;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Store\Entities\InstagramPost;

/**
 * The storefront in its three languages (Arabic and Hebrew right to left, English left to right),
 * each page in the visitor's language, and the content staff publish: a campaign page and the
 * Instagram section.
 */
class StorefrontLocalesTest extends E2ETestCase
{
    public function test_key_pages_speak_each_language_in_its_direction(): void
    {
        $demo = $this->demo();
        $cart = $this->addToCart($demo->item('oxford-shirt', 'white', 'M'), 1);
        $names = ['ar' => 'قميص أكسفورد كلاسيكي', 'en' => 'Classic Oxford shirt', 'he' => 'חולצת אוקספורד קלאסית'];
        $campaigns = ['ar' => 'تخفيضات الصيف', 'en' => 'Summer sale', 'he' => 'מבצע קיץ'];

        foreach (['ar' => 'rtl', 'en' => 'ltr', 'he' => 'rtl'] as $locale => $dir) {
            $pages = [
                "/{$locale}" => 'Store/Storefront/Home',
                "/{$locale}/shop" => 'Store/Storefront/Shop',
                "/{$locale}/categories" => 'Store/Storefront/Categories',
                "/{$locale}/category/".$demo->categories['shirts']->slug => 'Store/Storefront/Category',
                "/{$locale}/product/".$demo->products['oxford-shirt']->slug => 'Store/Storefront/ProductPage',
                "/{$locale}/product/".$demo->bundles['summer-set']->slug => 'Store/Storefront/BundlePage',
                "/{$locale}/cart" => 'Store/Storefront/Cart',
                "/{$locale}/checkout" => 'Store/Storefront/Checkout',
                "/{$locale}/track" => 'Store/Storefront/TrackOrder',
                "/{$locale}/campaign/".$demo->extra['campaign']->slug => 'Store/Storefront/Campaign',
                "/{$locale}/account/login" => 'Store/Storefront/Account/Login',
                "/{$locale}/wholesale/apply" => 'Wholesale/Portal/Apply',
            ];

            foreach ($pages as $path => $component) {
                $response = $this->withCookie('store_cart', $cart)->get($this->siteUrl($path))->assertOk();
                $this->assertStringContainsString("<html lang=\"{$locale}\" dir=\"{$dir}\">", $response->getContent(), $path);
                $props = $this->page($path, $component, cart: $cart);
                $this->assertSame([$locale, $dir], [$props['locale'], $props['dir']], $path);
            }

            $product = $this->page("/{$locale}/product/".$demo->products['oxford-shirt']->slug, 'Store/Storefront/ProductPage');
            $this->assertSame($names[$locale], $product['product']['name']);
            $this->assertSame($campaigns[$locale], $this->page("/{$locale}/campaign/".$demo->extra['campaign']->slug, 'Store/Storefront/Campaign')['campaign']['title']);
        }
    }

    public function test_the_campaign_page_and_the_instagram_section(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $demo = $this->demo();
        $campaign = $demo->extra['campaign'];

        $blocks = $this->page('/en/campaign/'.$campaign->slug, 'Store/Storefront/Campaign')['blocks'];
        $this->assertSame(['text', 'products', 'cta'], array_column($blocks, 'type'));
        $this->assertStringContainsString('Summer sale', json_encode($blocks));
        $this->assertNotEmpty(json_encode($blocks));
        $this->assertStringContainsString((string) $demo->products['linen-shirt']->translate('en')->name, json_encode($blocks, JSON_UNESCAPED_UNICODE));

        // A draft is not public; staff see it as a preview.
        $this->actingAs($this->admin())->put($this->siteUrl('/ar/admin/store/campaigns/'.$campaign->id), [
            'title' => 'تخفيضات الصيف', 'slug' => $campaign->slug, 'status' => 'draft',
            'blocks' => [['type' => 'text', 'content' => ['html' => '<p>قريباً</p>']]],
        ])->assertSessionHasNoErrors();
        $this->asGuest()->get($this->siteUrl('/ar/campaign/'.$campaign->slug))->assertInertia(fn ($page) => $page->component('Store/Storefront/NotFound')->etc());
        $this->assertTrue($this->page('/ar/campaign/'.$campaign->slug, 'Store/Storefront/Campaign', $this->admin())['campaign']['preview']);

        // Instagram: staff add a post tagged with a product and switch the home section on.
        $this->assertNull($this->asGuest()->page('/ar', 'Store/Storefront/Home')['instagram']);
        $this->actingAs($this->admin())->post($this->siteUrl('/ar/admin/store/instagram'), [
            'permalink' => 'https://www.instagram.com/p/E2E1/', 'caption' => 'إطلالة الصيف', 'is_active' => true,
            'products' => [$demo->products['linen-shirt']->id], 'image' => UploadedFile::fake()->image('post.jpg', 600, 600),
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->put($this->siteUrl('/ar/admin/store/instagram/settings'), ['enabled' => true, 'title' => 'تابعونا'])->assertSessionHasNoErrors();
        $this->assertSame(1, InstagramPost::query()->count());

        $this->asGuest();
        $home = $this->page('/ar', 'Store/Storefront/Home')['instagram'];
        $this->assertSame('تابعونا', $home['title']);
        $this->assertSame('https://www.instagram.com/p/E2E1/', $home['posts'][0]['permalink'] ?? $home['posts'][0]['url'] ?? null);
    }
}
