<?php

namespace Tests\Feature\Store;

use Illuminate\Testing\TestResponse;
use Modules\Items\Entities\Product;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\StockAlert;
use Modules\Store\Support\CartCookie;
use Tests\Feature\Items\Concerns\MakesBundles;

/**
 * A set on the storefront: its page lists the components and lets the buyer pick each one's size;
 * the price is the set's own; what can be ordered comes from the components' stock.
 */
class BundleStorefrontTest extends StorefrontTestCase
{
    use MakesBundles;

    private Product $set;

    /** @var array<string, Product> */
    private array $shirts;

    private Product $trousers;

    protected function setUp(): void
    {
        parent::setUp();

        [$shirt, $this->shirts] = $this->sized('قميص', ['M' => 3, 'L' => 0], 120);
        $this->trousers = Product::factory()->create(['name' => 'بنطال', 'base_price' => 200, 'manage_stock' => false]);
        $this->set = $this->bundle([[$shirt, 1], [$this->trousers, 1]], ['name' => 'طقم الصيف', 'slug' => 'summer-set', 'base_price' => 300]);
    }

    public function test_the_bundle_page_lists_components_and_their_choices(): void
    {
        $props = $this->props('/ar/product/summer-set', 'Store/Storefront/BundlePage');

        $this->assertSame('طقم الصيف', $props['product']['name']);
        [$shirt, $trousers] = $props['components'];
        $this->assertSame('قميص', $shirt['name']);
        $this->assertTrue($shirt['choosable']);
        $this->assertSame(['M', 'L'], array_column($shirt['attributes'][0]['values'], 'name'));
        $this->assertSame([true, false], array_column($shirt['variants'], 'in_stock'));
        $this->assertSame('بنطال', $trousers['name']);
        $this->assertFalse($trousers['choosable']);

        $this->assertSame('300.00 ₪', $props['quote']['price']['label']);
        $this->assertFalse($props['quote']['ready']);
        $this->assertSame(['اختر مقاس قميص'], $props['quote']['problems']);
        $this->assertTrue($props['quote']['in_stock']);
        $this->assertTrue($props['canAddToCart']);
    }

    public function test_the_quote_needs_every_choice_and_caps_by_stock(): void
    {
        $component = $this->set->bundleComponents()->first();
        $props = $this->props('/ar/product/summer-set?components['.$component->id.']='.$this->shirts['M']->id.'&quantity=9', 'Store/Storefront/BundlePage');

        $this->assertTrue($props['quote']['ready']);
        $this->assertSame([], $props['quote']['problems']);
        $this->assertSame(3, $props['quote']['max_quantity']);
        $this->assertSame(3, $props['quote']['quantity']);
        $this->assertSame('900.00 ₪', $props['quote']['total_label']);

        $props = $this->props('/ar/product/summer-set?components['.$component->id.']='.$this->shirts['L']->id, 'Store/Storefront/BundlePage');
        $this->assertFalse($props['quote']['ready']);
        $this->assertSame(['غير متوفر حالياً'], $props['quote']['problems']);
    }

    public function test_a_choice_must_be_a_variant_of_its_component(): void
    {
        $component = $this->set->bundleComponents()->first();

        $this->add([])->assertSessionHasErrors('components');
        $this->add([$component->id => $this->trousers->id])->assertSessionHasErrors('components');
        $this->add([$component->id => $component->product_id])->assertSessionHasErrors('components');
        $this->add([$component->id => $this->shirts['L']->id])->assertSessionHasErrors('quantity');
        $this->assertSame(0, Cart::query()->count());

        $this->add([$component->id => $this->shirts['M']->id])->assertSessionHasNoErrors();
        $this->assertSame(1, Cart::query()->sole()->items()->count());
    }

    public function test_the_same_bundle_with_other_choices_is_another_line(): void
    {
        $component = $this->set->bundleComponents()->first();
        $this->shirts['L']->update(['stock_quantity' => 2, 'stock_status' => 'in_stock']);

        $token = $this->token($this->add([$component->id => $this->shirts['M']->id]));
        $this->add([$component->id => $this->shirts['M']->id], 1, $token)->assertSessionHasNoErrors();
        $this->add([$component->id => $this->shirts['L']->id], 2, $token)->assertSessionHasNoErrors();
        $this->add([$component->id => $this->shirts['L']->id], 1, $token)->assertSessionHasErrors('quantity'); // only 2 large shirts

        $lines = $this->withCookie(CartCookie::NAME, $token)->props('/ar/cart', 'Store/Storefront/Cart')['lines'];

        $this->assertCount(2, $lines);
        $this->assertSame([2, 2], array_column($lines, 'quantity'));
        $this->assertSame('طقم الصيف', $lines[0]['name']);
        $this->assertSame([['name' => 'قميص', 'value' => 'M'], ['name' => 'بنطال', 'value' => '']], $lines[0]['options']);
        $this->assertSame('600.00 ₪', $lines[0]['total_label']);
        $this->assertSame(3, $lines[0]['max_quantity']);
        $this->assertSame(2, $lines[1]['max_quantity']);
        $this->assertTrue($lines[0]['bundle']);
    }

    public function test_cards_and_badges_follow_the_components(): void
    {
        $card = fn () => collect($this->props('/ar/shop', 'Store/Storefront/Shop')['products']['data'])->firstWhere('id', $this->set->id);

        $this->assertSame('300.00 ₪', $card()['price']['label']);
        $this->assertContains('limited', array_column($card()['badges'], 'key'), 'three sets can be sold');

        $this->shirts['M']->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $this->assertContains('out_of_stock', array_column($card()['badges'], 'key'));
    }

    public function test_search_finds_a_bundle(): void
    {
        $names = array_column($this->props('/ar/shop?q=الصيف', 'Store/Storefront/Shop')['products']['data'], 'name');

        $this->assertSame(['طقم الصيف'], $names);
    }

    public function test_notify_me_for_a_bundle(): void
    {
        $this->post($this->siteUrl('/ar/stock-alerts'), ['product_id' => $this->set->id, 'email' => 'buyer@example.com'])->assertSessionHasErrors('product_id');

        $this->shirts['M']->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $this->post($this->siteUrl('/ar/stock-alerts'), ['product_id' => $this->set->id, 'email' => 'buyer@example.com'])->assertSessionHasNoErrors();

        $this->assertSame([$this->set->id], StockAlert::query()->pluck('product_id')->all());
    }

    /**
     * @param  array<int, int>  $components
     */
    private function add(array $components, int $quantity = 1, ?string $token = null): TestResponse
    {
        $request = $token ? $this->withCookie(CartCookie::NAME, $token) : $this;

        return $request->from($this->siteUrl('/ar/product/summer-set'))
            ->post($this->siteUrl('/ar/cart'), ['product_id' => $this->set->id, 'quantity' => $quantity, 'components' => $components]);
    }

    private function token(TestResponse $response): string
    {
        return (string) $response->getCookie(CartCookie::NAME)?->getValue();
    }
}
