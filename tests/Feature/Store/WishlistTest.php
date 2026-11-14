<?php

namespace Tests\Feature\Store;

use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductStatus;
use Modules\Store\Entities\WishlistItem;

/**
 * The heart on cards and the product page, and the account's wishlist page.
 */
class WishlistTest extends StorefrontTestCase
{
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer@example.com', 'password' => 'Secret-pass-123'])
            ->assignRole(UserRole::CUSTOMER->value);
    }

    public function test_toggle_adds_and_removes(): void
    {
        $product = Product::factory()->create(['name' => 'قميص']);
        $this->actingAs($this->buyer);

        $this->post($this->siteUrl('/ar/wishlist'), ['product_id' => $product->id])->assertSessionHasNoErrors();
        $this->assertSame([$product->id], WishlistItem::query()->pluck('product_id')->all());

        $this->post($this->siteUrl('/ar/wishlist'), ['product_id' => $product->id])->assertSessionHasNoErrors();
        $this->assertSame(0, WishlistItem::query()->count());
    }

    public function test_only_parents_and_single_products_the_storefront_shows(): void
    {
        $shirt = Product::factory()->create(['name' => 'قميص']);
        $variant = Product::factory()->variationOf($shirt)->create();
        $draft = Product::factory()->create(['status' => ProductStatus::DRAFT]);
        $this->actingAs($this->buyer);

        $this->post($this->siteUrl('/ar/wishlist'), ['product_id' => $variant->id])->assertSessionHasErrors('product_id');
        $this->post($this->siteUrl('/ar/wishlist'), ['product_id' => $draft->id])->assertSessionHasErrors('product_id');
        $this->assertSame(0, WishlistItem::query()->count());
    }

    public function test_the_page_lists_cards_and_the_layout_lists_the_ids(): void
    {
        $first = Product::factory()->create(['name' => 'قميص']);
        $second = Product::factory()->create(['name' => 'بنطال']);
        $this->actingAs($this->buyer);
        $this->post($this->siteUrl('/ar/wishlist'), ['product_id' => $first->id]);
        $this->post($this->siteUrl('/ar/wishlist'), ['product_id' => $second->id]);
        $first->update(['status' => ProductStatus::DRAFT]); // left on the list, not shown

        $props = $this->props('/ar/account/wishlist', 'Store/Storefront/Account/Wishlist');

        $this->assertSame(['بنطال'], array_column($props['cards'], 'name'));
        $this->assertSame([$second->id, $first->id], $props['store']['wishlist']);
    }

    public function test_guests_have_no_ids_and_are_sent_to_login(): void
    {
        $product = Product::factory()->create();

        $this->assertSame([], $this->props('/ar', 'Store/Storefront/Home')['store']['wishlist']);
        $this->post($this->siteUrl('/ar/wishlist'), ['product_id' => $product->id])->assertRedirect(route('store.account.login'));
        $this->assertSame(0, WishlistItem::query()->count());
    }
}
