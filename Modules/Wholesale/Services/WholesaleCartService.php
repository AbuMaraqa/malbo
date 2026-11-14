<?php

namespace Modules\Wholesale\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Store\Support\Price;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\WholesaleCart;
use Modules\Wholesale\Entities\WholesaleCartItem;
use Modules\Wholesale\Repositories\Contracts\WholesaleCartRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesaleProductRepositoryInterface;
use Modules\Wholesale\Settings\WholesaleSettings;
use Modules\Wholesale\Support\PriceBook;
use Modules\Wholesale\Support\WholesaleItems;

/**
 * A merchant's wholesale cart, apart from the storefront's. It is written a product at a time: the
 * quantities of each of its sizes, checked on the server (sold wholesale to this merchant, in stock,
 * and whole size runs for a product not sold by the piece). Every read prices the lines again for
 * the merchant's tier, with the quantity break the product's total reaches, and flags what can no
 * longer be ordered. Nothing is reserved: checkout takes the stock.
 */
class WholesaleCartService
{
    /** What an order's money columns hold (decimal(10, 2)), as OrderService allows. */
    public const MONEY_LIMIT = 99999999.99;

    public function __construct(
        private readonly WholesaleCartRepositoryInterface $carts,
        private readonly WholesaleProductRepositoryInterface $wholesaleProducts,
        private readonly ProductRepositoryInterface $products,
        private readonly WholesalePricing $pricing,
    ) {}

    public function current(int $userId): ?WholesaleCart
    {
        return $this->carts->findOpenForUser($userId);
    }

    /**
     * @return array<int, int> item id => pieces
     */
    public static function quantities(?WholesaleCart $cart): array
    {
        return $cart ? $cart->items->mapWithKeys(fn (WholesaleCartItem $item) => [(int) $item->product_id => (int) $item->quantity])->all() : [];
    }

    /**
     * The cart's pieces of one product become these (item id => pieces; 0 or missing takes the size
     * out). The cart is opened on the first product.
     *
     * @param  array<int|string, int|string>  $quantities
     *
     * @throws ValidationException on `quantities`
     */
    public function set(Merchant $merchant, int $productId, array $quantities): void
    {
        $product = $this->wholesaleProducts->findPublished($productId);

        if ($product === null || $product->parent_id !== null) {
            throw ValidationException::withMessages(['quantities' => __('This product is no longer available.')]);
        }

        $items = collect(WholesaleItems::of($product))->keyBy('id');
        $wanted = [];

        foreach ($quantities as $itemId => $quantity) {
            if (! $items->has((int) $itemId)) {
                throw ValidationException::withMessages(['quantities' => __('Choose sizes of this product only.')]);
            }

            if ((int) $quantity > 0) {
                $wanted[(int) $itemId] = (int) $quantity;
            }
        }

        $book = $this->pricing->book($this->pricing->tierFor($merchant), $items->values());

        foreach ($wanted as $itemId => $quantity) {
            $item = $items->get($itemId);

            if ($problem = $this->problem($item, $book, $quantity)) {
                throw ValidationException::withMessages(['quantities' => self::label($product, $item).': '.$problem]);
            }
        }

        if (! self::byPiece($product) && ($problem = WholesaleItems::runProblem($items->values()->all(), fn (Product $item) => self::orderable($item, $book), $wanted))) {
            throw ValidationException::withMessages(['quantities' => $problem]);
        }

        $this->assertFits($merchant, $product, $items->all(), $book, $wanted);

        $family = $this->wholesaleProducts->familyIds((int) $product->id);
        $cart = $this->open((int) $merchant->user_id);

        DB::transaction(function () use ($cart, $family, $wanted): void {
            $locked = $this->lockOpen($cart);

            $locked->items()->whereIn('product_id', $family)->delete();

            foreach ($wanted as $itemId => $quantity) {
                $locked->items()->create(['product_id' => $itemId, 'quantity' => $quantity]);
            }

            $locked->update(['last_activity_at' => Carbon::now()]);
        });
    }

    /**
     * Takes a product (all its sizes) out of the cart.
     */
    public function remove(int $userId, int $productId): void
    {
        $cart = $this->current($userId);

        if ($cart === null) {
            return;
        }

        $family = $this->wholesaleProducts->familyIds($productId);

        DB::transaction(function () use ($cart, $family): void {
            $locked = $this->lockOpen($cart);
            $locked->items()->whereIn('product_id', $family)->delete();
            $locked->update(['last_activity_at' => Carbon::now()]);
        });
    }

    /**
     * The cart became this order: it is closed; the next product opens a new one.
     */
    public function markConverted(WholesaleCart $cart, Order $order): void
    {
        $cart->update(['order_id' => $order->id, 'converted_at' => Carbon::now(), 'last_activity_at' => Carbon::now()]);
    }

    /**
     * The cart's products, each with its sizes, priced now for the merchant.
     *
     * @return array{groups: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function read(WholesaleCart $cart, Merchant $merchant): array
    {
        $ids = $cart->items->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $named = $this->products->forCart($ids);
        $groupIds = collect($ids)->map(fn (int $id) => (int) ($named->get($id)?->parent_id ?: $id))->unique()->values()->all();
        $trees = $this->wholesaleProducts->publishedTrees($groupIds);

        /** @var Collection<int, Product> $sellable item id => item, as the trees offer them */
        $sellable = $trees->flatMap(fn (Product $tree) => WholesaleItems::of($tree))->keyBy('id');
        $book = $this->pricing->book($this->pricing->tierFor($merchant), [...$sellable->values()->all(), ...$named->values()->all()]);

        $raw = [];

        foreach ($cart->items as $line) {
            $id = (int) $line->product_id;
            $item = $sellable->get($id);
            $quantity = (int) $line->quantity;
            $raw[] = [
                'line' => $line,
                'group' => (int) ($named->get($id)?->parent_id ?: $id),
                'item' => $item,
                'problem' => $item ? $this->problem($item, $book, $quantity) : __('This product is no longer available.'),
                'quantity' => $quantity,
            ];
        }

        $totals = [];

        foreach ($raw as $row) {
            if ($row['problem'] === null) {
                $totals[$row['group']] = ($totals[$row['group']] ?? 0) + $row['quantity'];
            }
        }

        $groups = [];

        foreach ($raw as $row) {
            $groupId = $row['group'];
            $tree = $trees->get($groupId);
            $shown = $named->get($row['line']->product_id);
            $parent = $tree ?? $shown?->parent ?? $shown;
            $groupQuantity = $totals[$groupId] ?? 0;
            $unit = $row['item'] ? $book->unit($row['item'], max($groupQuantity, $row['quantity'])) : null;
            $total = $row['problem'] === null && $unit !== null ? round($unit * $row['quantity'], 2) : 0.0;
            $limit = $row['item'] ? WholesaleItems::limit($row['item']) : 0;
            $inStock = $row['item'] ? WholesaleItems::inStock($row['item']) : 0;
            $incoming = $row['item'] ? WholesaleItems::incoming($row['item']) : null;

            $groups[$groupId] ??= [
                'product_id' => $groupId,
                'name' => (string) ($parent?->name ?? ''),
                'url' => $tree ? route('store.wholesale.product', ['slug' => $tree->slug]) : null,
                'image' => $tree?->getFirstMediaUrl('product') ?: null,
                'by_piece' => $tree ? self::byPiece($tree) : true,
                'quantity' => 0,
                'total' => 0.0,
                'break_percent' => $book->breakPercent($groupId, $groupQuantity),
                'problem' => null,
                'lines' => [],
            ];

            $groups[$groupId]['lines'][] = [
                'id' => (int) $row['line']->id,
                'product_id' => (int) $row['line']->product_id,
                'options' => $shown ? self::options($shown) : [],
                'sku' => $shown?->sku,
                'quantity' => $row['quantity'],
                'available' => $limit,
                // Pieces beyond the stock now: reserved from the incoming goods when ordered.
                'preorder' => $inStock !== null && $row['problem'] === null ? max(0, $row['quantity'] - $inStock) : 0,
                'incoming_label' => $incoming['label'] ?? null,
                'unit_price' => $unit,
                'unit_label' => $unit !== null ? (new Price($unit))->format() : null,
                'total' => $total,
                'total_label' => (new Price($total))->format(),
                'problem' => $row['problem'],
            ];

            $groups[$groupId]['quantity'] += $row['quantity'];
            $groups[$groupId]['total'] = round($groups[$groupId]['total'] + $total, 2);
        }

        foreach ($groups as $groupId => &$group) {
            $tree = $trees->get($groupId);
            $group['total_label'] = (new Price($group['total']))->format();
            $group['break_label'] = $group['break_percent'] > 0
                ? __(':percent% quantity discount', ['percent' => rtrim(rtrim(number_format($group['break_percent'], 2), '0'), '.')])
                : null;

            if ($tree && ! $group['by_piece']) {
                $quantities = collect($group['lines'])->pluck('quantity', 'product_id')->map(fn ($quantity) => (int) $quantity)->all();
                $group['problem'] = WholesaleItems::runProblem(WholesaleItems::of($tree), fn (Product $item) => self::orderable($item, $book), $quantities);
            }
        }
        unset($group);

        $groups = array_values($groups);

        return ['groups' => $groups, 'summary' => $this->summary($groups, $merchant)];
    }

    /**
     * The order's figures, the minimum it must reach, and whether it can be placed.
     *
     * @param  list<array<string, mixed>>  $groups
     * @return array<string, mixed>
     */
    public function summary(array $groups, Merchant $merchant): array
    {
        $blocked = false;
        $subtotal = 0.0;
        $pieces = 0;

        foreach ($groups as $group) {
            $blocked = $blocked || $group['problem'] !== null;

            foreach ($group['lines'] as $line) {
                if ($line['problem'] !== null) {
                    $blocked = true;

                    continue;
                }

                $subtotal += $line['total'];
                $pieces += $line['quantity'];
            }
        }

        $subtotal = round($subtotal, 2);
        $minimum = $this->minimum($merchant);
        $missing = [];
        $tooLarge = $subtotal > self::MONEY_LIMIT ? self::tooLargeMessage() : null;

        if ($minimum['value'] !== null && $subtotal < $minimum['value']) {
            $missing[] = __('The minimum order is :minimum: add :missing more.', [
                'minimum' => (new Price($minimum['value']))->format(),
                'missing' => (new Price(round($minimum['value'] - $subtotal, 2)))->format(),
            ]);
        }

        if ($minimum['pieces'] !== null && $pieces < $minimum['pieces']) {
            $missing[] = __('The minimum order is :minimum pieces: add :missing more.', [
                'minimum' => $minimum['pieces'],
                'missing' => $minimum['pieces'] - $pieces,
            ]);
        }

        return [
            'subtotal' => $subtotal,
            'subtotal_label' => (new Price($subtotal))->format(),
            'pieces' => $pieces,
            'minimum_value_label' => $minimum['value'] !== null ? (new Price($minimum['value']))->format() : null,
            'minimum_pieces' => $minimum['pieces'],
            'minimum_missing' => $missing,
            // More than an order can hold: split it into smaller orders.
            'too_large' => $tooLarge,
            'blocked' => $blocked || $tooLarge !== null,
            'can_checkout' => $groups !== [] && ! $blocked && $tooLarge === null && $missing === [],
        ];
    }

    private static function tooLargeMessage(): string
    {
        return __('This order is larger than one order can hold (:limit). Split it into smaller orders.', ['limit' => (new Price(self::MONEY_LIMIT))->format()]);
    }

    /**
     * The cart with this product's new quantities stays within what an order can hold: each line
     * and the whole subtotal.
     *
     * @param  array<int, Product>  $items  the product's items, by id
     * @param  array<int, int>  $wanted  item id => pieces
     *
     * @throws ValidationException on `quantities`
     */
    private function assertFits(Merchant $merchant, Product $product, array $items, PriceBook $book, array $wanted): void
    {
        $pieces = array_sum($wanted);
        $total = 0.0;

        foreach ($wanted as $itemId => $quantity) {
            $total += round((float) $book->unit($items[$itemId], $pieces) * $quantity, 2);
        }

        $cart = $this->current((int) $merchant->user_id);

        if ($cart !== null) {
            $family = $this->wholesaleProducts->familyIds((int) $product->id);
            $others = $cart->items->reject(fn (WholesaleCartItem $item) => in_array((int) $item->product_id, $family, true));

            if ($others->isNotEmpty()) {
                $cart->setRelation('items', $others->values());
                $total += $this->read($cart, $merchant)['summary']['subtotal'];
            }
        }

        if (round($total, 2) > self::MONEY_LIMIT) {
            throw ValidationException::withMessages(['quantities' => self::tooLargeMessage()]);
        }
    }

    /**
     * The merchant's own minimum, else the store's (each part on its own).
     *
     * @return array{value: ?float, pieces: ?int}
     */
    public function minimum(Merchant $merchant): array
    {
        $settings = app(WholesaleSettings::class);

        return [
            'value' => $merchant->minimum_order_value !== null ? (float) $merchant->minimum_order_value : ($settings->minimum_order_value !== null ? (float) $settings->minimum_order_value : null),
            'pieces' => $merchant->minimum_order_pieces !== null ? (int) $merchant->minimum_order_pieces : $settings->minimum_order_pieces,
        ];
    }

    /**
     * Why this item cannot be ordered in this quantity now, or null.
     */
    private function problem(Product $item, PriceBook $book, int $quantity): ?string
    {
        if ($book->base($item) === null) {
            return __('This product is not sold wholesale.');
        }

        $limit = WholesaleItems::limit($item);

        if ($limit === 0) {
            return __('Currently unavailable');
        }

        if ($limit !== null && $quantity > $limit) {
            return __('Only :count left. Lower the quantity.', ['count' => $limit]);
        }

        return null;
    }

    private static function orderable(Product $item, PriceBook $book): bool
    {
        return $book->base($item) !== null && WholesaleItems::limit($item) !== 0;
    }

    private static function byPiece(Product $product): bool
    {
        return (bool) ($product->wholesale_by_piece ?? true);
    }

    private static function label(Product $product, Product $item): string
    {
        $size = $item->id === $product->id ? null : collect([WholesaleItems::colorValue($item)?->name, WholesaleItems::sizeLabel($item)])->filter()->implode(' / ');

        return $size ? $product->name.' ('.$size.')' : (string) $product->name;
    }

    /**
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

    /**
     * The user's open cart, opened now if there is none. Two requests opening one at once: the
     * partial unique index keeps one, and the second finds it.
     */
    private function open(int $userId): WholesaleCart
    {
        $cart = $this->carts->findOpenForUser($userId);

        if ($cart !== null) {
            return $cart;
        }

        try {
            /** @var WholesaleCart */
            return DB::transaction(fn () => $this->carts->create(['user_id' => $userId, 'last_activity_at' => Carbon::now()]));
        } catch (UniqueConstraintViolationException) {
            return $this->carts->findOpenForUser($userId) ?? throw ValidationException::withMessages(['quantities' => __('Try again.')]);
        }
    }

    /**
     * @throws ValidationException when the cart was ordered meanwhile
     */
    private function lockOpen(WholesaleCart $cart): WholesaleCart
    {
        $locked = $this->carts->lock($cart->id);

        if ($locked === null || $locked->order_id !== null || $locked->converted_at !== null) {
            throw ValidationException::withMessages(['quantities' => __('This cart was already ordered.')]);
        }

        return $locked;
    }
}
