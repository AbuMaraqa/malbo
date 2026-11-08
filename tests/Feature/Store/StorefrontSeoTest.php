<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Services\PageService;

/**
 * What the pages send to <StorefrontSeo>. The tags in the real SSR HTML are checked on a build.
 */
class StorefrontSeoTest extends StorefrontTestCase
{
    public function test_sitemap_lists_published_things_in_both_locales(): void
    {
        Product::factory()->create(['slug' => 'visible-mug']);
        Product::factory()->draft()->create(['slug' => 'draft-mug']);
        Category::factory()->create(['slug' => 'cups']);
        app(PageService::class)->setPublished(app(PageRepositoryInterface::class)->findByKey('terms'), false);

        $xml = $this->get($this->siteUrl('/sitemap.xml'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertNotFalse(simplexml_load_string($xml), 'valid XML');
        foreach (['/ar/product/visible-mug', '/en/product/visible-mug', '/ar/category/cups', '/ar/page/privacy-policy', '/en/contact', '/ar/shop'] as $path) {
            $this->assertStringContainsString($path.'<', $xml);
        }
        $this->assertStringNotContainsString('draft-mug', $xml);
        $this->assertStringNotContainsString('/page/terms<', $xml);
    }

    public function test_robots_points_a_store_at_its_sitemap(): void
    {
        $this->get($this->siteUrl('/robots.txt'))
            ->assertOk()
            ->assertSee('Sitemap: '.$this->siteUrl('/sitemap.xml'), false)
            ->assertSee('Disallow: /*/admin', false);
    }

    public function test_product_page_has_share_tags_and_structured_data(): void
    {
        $this->siteName('Maison');
        Product::factory()->create(['name' => 'كوب فخار', 'slug' => 'clay-cup', 'base_price' => 40, 'sale_price' => 35, 'manage_stock' => 0, 'short_description' => 'كوب مصنوع يدوياً']);

        $seo = $this->props('/ar/product/clay-cup', 'Store/Storefront/ProductPage')['seo'];

        $this->assertSame('كوب فخار — Maison', $seo['title']);
        $this->assertSame('product', $seo['og']['type']);
        $this->assertSame('كوب مصنوع يدوياً', $seo['description']);
        $this->assertSame($this->siteUrl('/ar/product/clay-cup'), $seo['canonical']);
        $this->assertSame($this->siteUrl('/en/product/clay-cup'), $seo['alternates']['en']);
        $this->assertSame($this->siteUrl('/ar/product/clay-cup'), $seo['alternates']['ar']);
        $this->assertSame($this->siteUrl('/ar/product/clay-cup'), $seo['x_default']);
        $this->assertFalse($seo['noindex']);

        $data = json_decode($seo['json_ld'][0], true);
        $this->assertSame('Product', $data['@type']);
        $this->assertSame('كوب فخار', $data['name']);
        $this->assertSame('35.00', $data['offers']['lowPrice']);
        $this->assertSame('ILS', $data['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
    }

    public function test_structured_data_cannot_close_its_script_tag(): void
    {
        Product::factory()->create(['name' => '</script><script>alert(1)</script>', 'slug' => 'evil', 'manage_stock' => 0]);

        $json = $this->props('/ar/product/evil', 'Store/Storefront/ProductPage')['seo']['json_ld'][0];

        $this->assertStringNotContainsString('</script>', $json);
        $this->assertSame('</script><script>alert(1)</script>', json_decode($json, true)['name']);
    }

    public function test_home_has_store_structured_data(): void
    {
        $seo = $this->props('/ar', 'Store/Storefront/Home')['seo'];

        $this->assertSame('Store', json_decode($seo['json_ld'][0], true)['@type']);
        $this->assertSame($this->siteUrl('/ar'), $seo['canonical']);
        $this->assertSame('ar_AR', $seo['og']['locale']);
    }

    public function test_the_canonical_drops_the_query_and_the_language_link_keeps_it(): void
    {
        $props = $this->props('/en/shop?q=x', 'Store/Storefront/Shop');

        $this->assertSame($this->siteUrl('/en/shop'), $props['seo']['canonical']);
        $this->assertSame('en_US', $props['seo']['og']['locale']);
        $this->assertSame($this->siteUrl('/ar/shop?q=x'), $props['store']['other_locale_url']);
    }

    public function test_a_missing_product_shows_the_store_404_page(): void
    {
        $this->assertStore404('/ar/product/nothing-here');
    }

    public function test_any_unknown_address_under_a_language_shows_the_store_404_page(): void
    {
        $this->assertStore404('/ar/nothing/here');
        $this->assertStore404('/en/page/nothing');
    }

    public function test_the_404_route_comes_after_every_other_language_route(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->reject->isFallback->map->uri()->values();
        $position = $uris->search('{locale}/{path}');

        $this->assertNotFalse($position);

        $later = $uris->slice($position + 1)->filter(fn (string $uri) => str_starts_with($uri, '{locale}/'));
        $this->assertSame([], $later->values()->all(), 'These routes would be hidden by the storefront 404');
    }

    public function test_addresses_without_a_language_still_redirect(): void
    {
        $this->get($this->siteUrl('/shop'))->assertRedirect($this->siteUrl('/ar/shop'));
    }

    public function test_storefront_props_do_not_list_admin_routes_or_admin_texts(): void
    {
        $props = $this->props('/ar', 'Store/Storefront/Home');

        $this->assertArrayNotHasKey('dashboard', $props['ziggy']['routes']);
        $this->assertArrayHasKey('store.shop', $props['ziggy']['routes']);
        $this->assertArrayHasKey('home', $props['ziggy']['routes']);
        $this->assertArrayNotHasKey('auth', $props);
    }

    private function assertStore404(string $path): void
    {
        $this->get($this->siteUrl($path))
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Storefront/NotFound')
                ->where('seo.noindex', true)
                ->where('seo.canonical', null)
                ->where('seo.alternates', [])
                ->has('store.name'));
    }
}
