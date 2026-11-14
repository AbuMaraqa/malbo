<?php

namespace Tests\Feature\Store;

use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Items\Entities\Product;
use Modules\Store\Entities\Cart;
use Modules\Store\Support\CartCookie;

/**
 * Signing in hands the browser's cart to the account, and the account's cart follows it.
 */
class CartMergeTest extends StorefrontTestCase
{
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer@example.com', 'password' => 'Secret-pass-123'])
            ->assignRole(UserRole::CUSTOMER->value);
    }

    public function test_a_guest_cart_becomes_the_users_cart(): void
    {
        $product = $this->product(10);
        $token = $this->add($product, 2);

        $this->login($token)->assertCookie(CartCookie::NAME, $token);

        $cart = Cart::query()->sole();
        $this->assertSame($this->buyer->id, $cart->user_id);
        $this->assertSame(2, $this->props('/ar', 'Store/Storefront/Home')['store']['cart_count']);
    }

    public function test_carts_merge_and_quantities_are_capped(): void
    {
        $shirt = $this->product(5);
        $trousers = $this->product(10);
        $mine = $this->userCart([$shirt->id => 4]);
        $token = $this->add($shirt, 3);
        $this->add($trousers, 1, $token);

        $this->login($token)->assertCookie(CartCookie::NAME, $mine->token);

        $this->assertSame(1, Cart::query()->count(), 'the guest cart is gone');
        $quantities = $mine->fresh()->items->pluck('quantity', 'product_id')->all();
        $this->assertSame([$shirt->id => 5, $trousers->id => 1], $quantities, 'capped by the stock of 5');
    }

    public function test_the_cart_follows_the_user_to_another_browser(): void
    {
        $product = $this->product(10);
        $this->userCart([$product->id => 3]);

        $this->actingAs($this->buyer);

        $this->assertSame(3, $this->props('/ar', 'Store/Storefront/Home')['store']['cart_count']);
        $this->assertSame(1, count($this->props('/ar/cart', 'Store/Storefront/Cart')['lines']));
    }

    public function test_another_users_cart_is_never_taken(): void
    {
        $product = $this->product(10);
        $other = User::forceCreate(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'Secret-pass-123']);
        $theirs = $this->userCart([$product->id => 2], $other);

        $this->login($theirs->token);

        $this->assertSame($other->id, $theirs->fresh()->user_id);
        $this->assertSame(0, $this->props('/ar', 'Store/Storefront/Home')['store']['cart_count']);
        // And a guest holding that cookie does not see it either.
        auth()->logout();
        $this->withCookie(CartCookie::NAME, $theirs->token);
        $this->assertSame(0, $this->props('/ar', 'Store/Storefront/Home')['store']['cart_count']);
    }

    public function test_a_cart_ordered_while_signing_in_is_not_merged_into(): void
    {
        $product = $this->product(10);
        $mine = $this->userCart([$product->id => 1]);
        $token = $this->add($product, 2);
        $order = \Modules\Items\Entities\Order::query()->create(['order_number' => 'RACE-1', 'sub_total' => 50, 'total' => 50, 'status' => 1]);

        // The account's cart becomes an order (another device's checkout) just after it was read.
        $this->app->bind(\Modules\Store\Repositories\Contracts\CartRepositoryInterface::class, fn ($app) => new class(new Cart, $order->id) extends \Modules\Store\Repositories\CartRepository
        {
            public function __construct(Cart $model, private int $orderId)
            {
                parent::__construct($model);
            }

            public function findOpenForUser(int $userId): ?Cart
            {
                $cart = parent::findOpenForUser($userId);
                $cart?->newQuery()->whereKey($cart->id)->update(['order_id' => $this->orderId]);

                return $cart;
            }
        });

        $this->login($token);

        $this->assertSame([$product->id => 1], $mine->fresh()->items->pluck('quantity', 'product_id')->all(), 'the ordered cart is untouched');
        $guest = Cart::query()->where('token', $token)->sole();
        $this->assertSame($this->buyer->id, $guest->user_id);
        $this->assertNull($guest->order_id);
        $this->assertSame([$product->id => 2], $guest->items->pluck('quantity', 'product_id')->all());
    }

    public function test_a_signed_in_users_new_cart_is_theirs_and_logout_leaves_it(): void
    {
        $product = $this->product(10);
        $this->actingAs($this->buyer);

        $this->post($this->siteUrl('/ar/cart'), ['product_id' => $product->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->assertSame($this->buyer->id, Cart::query()->sole()->user_id);

        $this->post($this->siteUrl('/ar/account/logout'))->assertCookieExpired(CartCookie::NAME);
    }

    private function product(int $stock): Product
    {
        return Product::factory()->create(['base_price' => 50, 'manage_stock' => true, 'stock_quantity' => $stock]);
    }

    /**
     * @param  array<int, int>  $lines
     */
    private function userCart(array $lines, ?User $user = null): Cart
    {
        $cart = Cart::query()->create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'token' => \Illuminate\Support\Str::random(48), 'user_id' => ($user ?? $this->buyer)->id, 'last_activity_at' => now()]);

        foreach ($lines as $productId => $quantity) {
            $cart->items()->create(['product_id' => $productId, 'quantity' => $quantity]);
        }

        return $cart;
    }

    private function add(Product $product, int $quantity, ?string $token = null): string
    {
        $request = $token ? $this->withCookie(CartCookie::NAME, $token) : $this;
        $response = $request->post($this->siteUrl('/ar/cart'), ['product_id' => $product->id, 'quantity' => $quantity])->assertSessionHasNoErrors();

        return $token ?? (string) $response->getCookie(CartCookie::NAME)?->getValue();
    }

    private function login(string $token): TestResponse
    {
        return $this->withCookie(CartCookie::NAME, $token)
            ->post($this->siteUrl('/ar/account/login'), ['email' => 'buyer@example.com', 'password' => 'Secret-pass-123'])
            ->assertSessionHasNoErrors();
    }
}
