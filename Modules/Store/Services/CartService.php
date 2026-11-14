<?php

namespace Modules\Store\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\BundleComponent;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Support\BundleStock;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\CartItem;
use Modules\Store\Repositories\Contracts\CartRepositoryInterface;
use Modules\Store\Support\CartCookie;
use Modules\Store\Support\Price;

/**
 * The storefront cart. Lines hold a product and a quantity; every read prices them with
 * PricingService, and checks them with ProductConfigurator's rules (in stock, the order limit),
 * without reserving any stock. A line whose product is gone, unpublished or out of stock stays in
 * the cart, flagged, and out of the subtotal.
 *
 * What can go in: a variant of a published, active product (itself active, none of its values
 * hidden), or a published, active product without active variants. Addons are not carried by the
 * cart, so a product that offers addons is ordered on WhatsApp.
 */
class CartService
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly ProductRepositoryInterface $products,
        private readonly PricingService $pricing,
        private readonly ProductConfigurator $configurator,
    ) {}

    /**
     * The open cart of this browser, if any. A cart that belongs to an account is only ever the
     * cart of that account: a guest (or another account) with its cookie does not get it. A
     * signed-in user without the cookie (another browser) gets their newest open cart.
     */
    public function current(Request $request): ?Cart
    {
        $userId = $request->user()?->getAuthIdentifier();
        $token = CartCookie::token($request);
        $cart = $token ? $this->carts->findOpenByToken($token) : null;

        if ($cart !== null && $cart->user_id !== null && (int) $cart->user_id !== (int) $userId) {
            $cart = null;
        }

        return $cart ?? ($userId ? $this->carts->findOpenForUser((int) $userId) : null);
    }

    /**
     * Signing in: the browser's guest cart and the account's open cart become one. The account's
     * cart stays; the same product's quantities are added, capped by what one order may hold (never
     * below what the account's cart already had); the guest cart is deleted. The cookie then holds
     * the account's cart, so it follows the account from browser to browser. A cart of another
     * account is never touched.
     */
    public function adopt(Request $request, User $user): void
    {
        $token = CartCookie::token($request);

        $this->join($token ? $this->carts->findOpenByToken($token) : null, $user, $token);
    }

    /**
     * A guest cart this browser reached another way (a reminder's restore link, $browserToken the
     * cookie it had) joins the account exactly as on sign-in: merged into the account's open cart,
     * or made the account's when it has none. A signed-in account is never handed a cart it does
     * not own.
     */
    public function adoptCart(Cart $guest, User $user, ?string $browserToken): void
    {
        $this->join($guest, $user, $browserToken);
    }

    private function join(?Cart $guest, User $user, ?string $token): void
    {
        if ($guest !== null && $guest->user_id !== null && (int) $guest->user_id !== (int) $user->id) {
            $guest = null;
        }

        $mine = $this->carts->findOpenForUser((int) $user->id);

        if ($guest === null || $mine === null || $guest->id === $mine->id) {
            $cart = $mine ?? $guest;

            if ($cart !== null) {
                if ($cart->user_id === null) {
                    $cart->update(['user_id' => $user->id, 'last_activity_at' => Carbon::now()]);
                }

                if ($cart->token !== $token) {
                    CartCookie::queue($cart->token);
                }
            }

            return;
        }

        $kept = DB::transaction(function () use ($guest, $mine, $user): ?Cart {
            $target = $this->carts->lock($mine->id);
            $source = $this->carts->lock($guest->id);

            if ($source === null || $source->order_id !== null) {
                return $target;
            }

            // The account's cart was ordered (another device's checkout) since it was read: the
            // guest cart becomes the account's open cart instead of being merged into an order.
            if ($target === null || $target->order_id !== null) {
                $source->update(['user_id' => $user->id, 'last_activity_at' => Carbon::now()]);

                return $source;
            }

            $products = $this->products->forCart($source->items->pluck('product_id')->map(fn ($id) => (int) $id)->all());

            foreach ($source->items as $item) {
                $line = self::lineOf($target, (int) $item->product_id, (string) $item->choice_key);
                $had = (int) ($line?->quantity ?? 0);
                $product = $products->get($item->product_id);
                $quantity = $had + (int) $item->quantity;

                if ($product !== null) {
                    $quantity = min($quantity, max($this->lineMax($product, $item->components), $had));
                }

                $line
                    ? $line->update(['quantity' => $quantity])
                    : $target->items()->create(['product_id' => $item->product_id, 'quantity' => $quantity, 'choice_key' => (string) $item->choice_key, 'components' => $item->components]);
            }

            $this->carts->delete($source);
            $target->update(['last_activity_at' => Carbon::now()]);

            return $target;
        });

        if ($kept !== null && $kept->token !== $token) {
            CartCookie::queue($kept->token);
        }
    }

    /**
     * This browser's cart, open or already ordered (a checkout sent twice finds its order).
     */
    public function latest(Request $request): ?Cart
    {
        $token = CartCookie::token($request);

        return $token ? $this->carts->findByToken($token) : null;
    }

    /**
     * Pieces in the open cart of this browser (the header's count).
     */
    public function count(Request $request): int
    {
        if ($request->user() !== null) {
            return (int) ($this->current($request)?->items->sum('quantity') ?? 0);
        }

        $token = CartCookie::token($request);

        return $token ? $this->carts->countByToken($token) : 0;
    }

    /**
     * Adds $quantity of a product; the same product already in the cart is merged into one line.
     * The cart and its cookie are created on the first add.
     *
     * A set or bundle takes $components (component id => the variant chosen); a component without a
     * choice to make takes its only item. The same bundle with the same choices is merged; other
     * choices are another line.
     *
     * @param  array<int|string, mixed>  $components
     *
     * @throws ValidationException on `product_id` (not for sale), `components` (a choice missing or
     *   not this bundle's) or `quantity` (over the limit)
     */
    public function add(Request $request, int $productId, int $quantity, array $components = []): Cart
    {
        $product = $this->products->forCart([$productId])->get($productId);
        $this->assertCanAdd($product);
        [$key, $chosen] = $this->choice($product, $components);

        $cart = $this->current($request);
        // Checked before a new cart is opened, and again on the locked cart below.
        $this->assertQuantity($product, (self::lineOf($cart, $product->id, $key)?->quantity ?? 0) + $quantity, $chosen);
        $cart ??= $this->open($request->user()?->getAuthIdentifier());

        DB::transaction(function () use ($cart, $product, $quantity, $key, $chosen): void {
            $locked = $this->carts->lock($cart->id);
            $line = self::lineOf($locked, $product->id, $key);

            $this->assertQuantity($product, ($line?->quantity ?? 0) + $quantity, $chosen);

            $line
                ? $line->update(['quantity' => $line->quantity + $quantity])
                : $locked->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'choice_key' => $key, 'components' => $chosen]);

            // The language the buyer shops in: a reminder speaks it.
            $locked->update(['last_activity_at' => Carbon::now(), 'locale' => app()->getLocale()]);
        });

        return $cart;
    }

    /**
     * @throws ValidationException on `quantity`
     */
    public function setQuantity(Cart $cart, CartItem $item, int $quantity): void
    {
        $product = $this->products->forCart([$item->product_id])->get($item->product_id);
        $this->assertCanAdd($product, 'quantity');
        $this->assertQuantity($product, $quantity, $item->components);

        DB::transaction(function () use ($cart, $item, $quantity): void {
            $locked = $this->lockOpen($cart);
            $locked->items->firstWhere('id', $item->id)?->update(['quantity' => $quantity]);
            $locked->update(['last_activity_at' => Carbon::now()]);
        });
    }

    public function remove(Cart $cart, CartItem $item): void
    {
        DB::transaction(function () use ($cart, $item): void {
            $locked = $this->lockOpen($cart);
            $locked->items->firstWhere('id', $item->id)?->delete();
            $locked->update(['last_activity_at' => Carbon::now()]);
        });
    }

    /**
     * The cart locked against a checkout running at the same time; refused once it became an order.
     *
     * @throws ValidationException on `quantity`
     */
    private function lockOpen(Cart $cart): Cart
    {
        $locked = $this->carts->lock($cart->id);

        if ($locked === null || $locked->order_id !== null) {
            throw ValidationException::withMessages(['quantity' => __('This cart was already ordered.')]);
        }

        return $locked;
    }

    /**
     * The cart became this order: it is closed, and this browser starts a new one next time.
     */
    public function markConverted(Cart $cart, Order $order): void
    {
        $cart->update(['order_id' => $order->id, 'converted_at' => Carbon::now(), 'last_activity_at' => Carbon::now()]);
        CartCookie::forget();
    }

    /**
     * The cart's lines, priced now.
     *
     * @return list<array{id: int, product_id: int, name: string, options: list<array{name: string, value: string}>, image: ?string, url: ?string, sku: ?string, price: array<string, mixed>, unit_price: float, quantity: int, max_quantity: int, total: float, total_label: string, available: bool, problem: ?string}>
     */
    public function lines(Cart $cart): array
    {
        $products = $this->products->forCart($cart->items->pluck('product_id')->map(fn ($id) => (int) $id)->all());

        return $cart->items->map(function (CartItem $item) use ($products): array {
            /** @var Product|null $product */
            $product = $products->get($item->product_id);
            $parent = $product?->parent ?? $product;
            $problem = $this->problem($product, $item->quantity, $item->components);
            $bundle = (bool) $product?->isBundle();
            $price = $product ? $this->pricing->for($product) : new Price(0);
            $total = $problem === null ? round($price->amount * $item->quantity, 2) : 0.0;

            return [
                'id' => $item->id,
                'product_id' => (int) $item->product_id,
                // What a coupon matches against: the product sold (a variant's parent) and its categories.
                'sellable_id' => $parent?->id,
                'category_ids' => $parent ? array_map('intval', $parent->categories->modelKeys()) : [],
                'name' => (string) ($parent?->name ?? ''),
                'options' => $product ? ($bundle ? $this->bundleOptions($product, $item->components) : self::options($product)) : [],
                'image' => $product ? ($product->getFirstMediaUrl('product') ?: $parent->getFirstMediaUrl('product') ?: null) : null,
                'url' => $parent && ! $parent->trashed() ? route('store.product', ['slug' => $parent->slug]) : null,
                'sku' => $product?->sku,
                'price' => $price->toArray(),
                'unit_price' => $price->amount,
                'quantity' => (int) $item->quantity,
                'max_quantity' => $product ? $this->lineMax($product, $item->components) : 1,
                'total' => $total,
                'total_label' => (new Price($total))->format(),
                'available' => $problem === null,
                'problem' => $problem,
                // A set or bundle: what one of it holds (the variant chosen per component), for checkout.
                'bundle' => $bundle,
                'components' => $bundle ? $this->bundleComponents($product, $item->components) : [],
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $lines  from lines()
     */
    public static function subtotal(array $lines): float
    {
        return round(array_sum(array_column(array_filter($lines, fn (array $line) => $line['available']), 'total')), 2);
    }

    /**
     * Why this product cannot be bought in this quantity now, or null when it can.
     */
    /**
     * @param  list<array{component_id: int, product_id: int}>|null  $components  a bundle line's choices
     */
    public function problem(?Product $product, int $quantity, ?array $components = null): ?string
    {
        if ($reason = $this->unavailable($product)) {
            return $reason;
        }

        if ($product->isBundle()) {
            return $this->bundleProblem($product, $quantity, $components ?? []);
        }

        $max = $this->configurator->maxQuantity($product);

        if ($quantity > $max) {
            return $product->manage_stock && ! $product->allow_backorder && (int) $product->stock_quantity <= $max
                ? __('Only :count left. Lower the quantity.', ['count' => (int) $product->stock_quantity])
                : __('You can order up to :max of this item.', ['max' => $max]);
        }

        return null;
    }

    private function open(int|string|null $userId = null): Cart
    {
        $token = Str::random(48);

        /** @var Cart $cart */
        $cart = $this->carts->create([
            'uuid' => (string) Str::uuid(),
            'token' => $token,
            'user_id' => $userId,
            'last_activity_at' => Carbon::now(),
            'locale' => app()->getLocale(),
        ]);

        CartCookie::queue($token);

        return $cart;
    }

    /**
     * @throws ValidationException
     */
    private function assertCanAdd(?Product $product, string $field = 'product_id'): void
    {
        if ($reason = $this->unavailable($product)) {
            throw ValidationException::withMessages([$field => $reason]);
        }
    }

    /**
     * @throws ValidationException
     */
    /**
     * @param  list<array{component_id: int, product_id: int}>|null  $components
     */
    private function assertQuantity(Product $product, int $quantity, ?array $components = null): void
    {
        if ($reason = $this->problem($product, $quantity, $components)) {
            throw ValidationException::withMessages(['quantity' => $reason]);
        }
    }

    /**
     * Why the product is not for sale at all, or null.
     */
    private function unavailable(?Product $product): ?string
    {
        if ($product === null || $product->trashed()) {
            return __('This product is no longer available.');
        }

        $parent = $product->parent;
        $sellable = $parent ?? $product;
        $active = fn (Product $item) => (int) $item->is_active === ProductActive::IS_ACTIVE->value;

        if (($product->parent_id && ($parent === null || $parent->trashed())) || $sellable->status !== ProductStatus::PUBLISHED || ! $active($sellable) || ! $active($product)) {
            return __('This product is no longer available.');
        }

        if ($product->parent_id && ! ProductConfigurator::visible($product)) {
            return __('This product is no longer available.');
        }

        if (! $product->parent_id && $product->variations->isNotEmpty()) {
            return __('Choose the options of this product first.');
        }

        if ($sellable->addons->isNotEmpty()) {
            return __('This product can only be ordered on WhatsApp.');
        }

        if (! ProductConfigurator::available($product)) {
            return __('Currently unavailable');
        }

        return null;
    }

    /**
     * The line of this product with these choices, if the cart has one.
     */
    private static function lineOf(?Cart $cart, int $productId, string $key): ?CartItem
    {
        return $cart?->items->first(fn (CartItem $item) => (int) $item->product_id === $productId && (string) $item->choice_key === $key);
    }

    /**
     * A bundle's choices checked (BundleStock::resolve), as the line keeps them, and their key; an
     * ordinary product has neither.
     *
     * @param  array<int|string, mixed>  $components
     * @return array{0: string, 1: list<array{component_id: int, product_id: int}>|null}
     *
     * @throws ValidationException on `components`
     */
    private function choice(Product $product, array $components): array
    {
        if (! $product->isBundle()) {
            return ['', null];
        }

        $resolved = BundleStock::resolve($product, BundlePage::cleanChoices($components));

        if ($missing = $resolved['missing'][0] ?? null) {
            throw ValidationException::withMessages(['components' => __('Choose the size of :name', ['name' => BundlePage::componentName($missing)])]);
        }

        if ($invalid = $resolved['invalid'][0] ?? null) {
            throw ValidationException::withMessages(['components' => __(':name is not available now.', ['name' => BundlePage::componentName($invalid)])]);
        }

        $rows = [];

        foreach ($resolved['chosen'] as $componentId => $item) {
            $rows[] = ['component_id' => (int) $componentId, 'product_id' => (int) $item->id];
        }

        return [md5(implode('|', array_map(fn (array $row) => $row['component_id'].':'.$row['product_id'], $rows))), $rows];
    }

    /**
     * The items a bundle line's saved choices stand for, or null when they no longer fit the bundle:
     * a component was added or removed, a piece is now another product, a chosen variant went. What
     * the buyer put in the cart is exactly what they get, or the line says so.
     *
     * @param  list<array{component_id: int, product_id: int}>  $components
     * @return array<int, Product>|null  component id => item
     */
    private static function bundleChosen(Product $bundle, array $components): ?array
    {
        $saved = array_map('intval', array_column($components, 'product_id', 'component_id'));
        $current = $bundle->bundleComponents->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $kept = array_keys($saved);
        sort($kept);

        if ($kept !== $current) {
            return null;
        }

        $resolved = BundleStock::resolve($bundle, $saved);

        if ($resolved['missing'] !== [] || $resolved['invalid'] !== []) {
            return null;
        }

        foreach ($resolved['chosen'] as $componentId => $item) {
            if ((int) $item->id !== $saved[$componentId]) {
                return null;
            }
        }

        return $resolved['chosen'];
    }

    /**
     * How many of a line one order may hold now: a bundle's chosen variants allow it (BundleStock),
     * capped like any product; anything else as ProductConfigurator says.
     *
     * @param  list<array{component_id: int, product_id: int}>|null  $components
     */
    private function lineMax(Product $product, ?array $components): int
    {
        if (! $product->isBundle()) {
            return $this->configurator->maxQuantity($product);
        }

        $chosen = self::bundleChosen($product, $components ?? []);
        $available = $chosen === null ? 0 : BundleStock::maxFor($product, $chosen);
        $cap = (int) ($product->max_quantity_per_order ?: ProductConfigurator::MAX_QUANTITY);

        return max(1, $available === null ? $cap : min($cap, $available));
    }

    /**
     * @param  list<array{component_id: int, product_id: int}>  $components
     */
    private function bundleProblem(Product $bundle, int $quantity, array $components): ?string
    {
        $chosen = self::bundleChosen($bundle, $components);

        if ($chosen === null) {
            return __('This choice is no longer available. Remove it and choose again.');
        }

        $available = BundleStock::maxFor($bundle, $chosen);
        $cap = (int) ($bundle->max_quantity_per_order ?: ProductConfigurator::MAX_QUANTITY);

        if ($available === 0) {
            return __('Currently unavailable');
        }

        if ($available !== null && $quantity > $available && $available <= $cap) {
            return __('Only :count left. Lower the quantity.', ['count' => $available]);
        }

        if ($quantity > $cap) {
            return __('You can order up to :max of this item.', ['max' => $cap]);
        }

        return null;
    }

    /**
     * A bundle line's components as the buyer reads them: the product (×2 when one bundle holds
     * two) and the variant chosen.
     *
     * @param  list<array{component_id: int, product_id: int}>|null  $components
     * @return list<array{name: string, value: string}>
     */
    private function bundleOptions(Product $bundle, ?array $components): array
    {
        $picked = array_column($components ?? [], 'product_id', 'component_id');

        return $bundle->bundleComponents->map(function (BundleComponent $component) use ($picked): array {
            $item = BundleStock::choices($component)->firstWhere('id', (int) ($picked[$component->id] ?? 0));

            return [
                'name' => BundlePage::componentName($component).((int) $component->quantity > 1 ? ' ×'.$component->quantity : ''),
                'value' => $item ? BundlePage::values($item) : '',
            ];
        })->values()->all();
    }

    /**
     * What one bundle of this line holds, for the order: each chosen item and how many of it.
     *
     * @param  list<array{component_id: int, product_id: int}>|null  $components
     * @return list<array{component_id: int, product_id: int, quantity: int}>
     */
    private function bundleComponents(Product $bundle, ?array $components): array
    {
        $rows = [];

        foreach (self::bundleChosen($bundle, $components ?? []) ?? [] as $componentId => $item) {
            $rows[] = [
                'component_id' => (int) $componentId,
                'product_id' => (int) $item->id,
                'quantity' => (int) $bundle->bundleComponents->firstWhere('id', $componentId)?->quantity,
            ];
        }

        return $rows;
    }

    /**
     * Color, size... of a variant, as the buyer reads them.
     *
     * @return list<array{name: string, value: string}>
     */
    private static function options(Product $product): array
    {
        return $product->attributeValues
            ->sortBy('attribute_id')
            ->map(fn (AttributeValue $value) => ['name' => (string) $value->attribute?->name, 'value' => (string) $value->name])
            ->values()
            ->all();
    }
}
