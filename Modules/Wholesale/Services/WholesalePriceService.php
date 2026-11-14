<?php

namespace Modules\Wholesale\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\Product;
use Modules\Items\Services\InventoryService;
use Modules\Wholesale\Repositories\Contracts\TierRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesalePriceRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesaleProductRepositoryInterface;

/**
 * Writes a product's wholesale terms from the admin's form, in one transaction: whether it is sold
 * by the piece, its items' prices per tier (an empty price removes the override) and its quantity
 * breaks (replaced as a whole). The product's own `wholesale_price` stays in the product form.
 */
class WholesalePriceService
{
    public function __construct(
        private readonly WholesalePriceRepositoryInterface $prices,
        private readonly WholesaleProductRepositoryInterface $products,
        private readonly TierRepositoryInterface $tiers,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @param  Product  $product  a parent (not a variation), from WholesaleProductRepository::forPriceEdit
     * @param  array{by_piece?: bool, prices?: array<int|string, array<int|string, mixed>>, breaks?: list<array{min_quantity: int|string, discount_percent: int|string|float}>}  $data
     *
     * @throws ValidationException on `prices` when an item or a tier is not this product's
     */
    public function save(Product $product, array $data): void
    {
        $items = $product->variations->isEmpty() ? [(int) $product->id] : array_map('intval', $product->variations->modelKeys());
        $tiers = array_map('intval', $this->tiers->query()->pluck('id')->all());

        $prices = [];

        foreach ($data['prices'] ?? [] as $itemId => $byTier) {
            if (! in_array((int) $itemId, $items, true)) {
                throw ValidationException::withMessages(['prices' => __('These prices belong to another product.')]);
            }

            foreach ((array) $byTier as $tierId => $price) {
                if (! in_array((int) $tierId, $tiers, true)) {
                    throw ValidationException::withMessages(['prices' => __('This price tier no longer exists.')]);
                }

                if (filled($price)) {
                    $prices[] = ['product_id' => (int) $itemId, 'tier_id' => (int) $tierId, 'price' => round((float) $price, 2)];
                }
            }
        }

        DB::transaction(function () use ($product, $data, $items, $prices): void {
            $this->products->setByPiece((int) $product->id, (bool) ($data['by_piece'] ?? true));

            $this->prices->query()->whereIn('product_id', $items)->delete();

            foreach ($prices as $row) {
                $this->prices->create($row);
            }

            $this->prices->breakQuery()->where('product_id', $product->id)->delete();

            foreach ($data['breaks'] ?? [] as $break) {
                $this->prices->breakQuery()->create([
                    'product_id' => $product->id,
                    'min_quantity' => (int) $break['min_quantity'],
                    'discount_percent' => round((float) $break['discount_percent'], 2),
                ]);
            }
        });
    }

    /**
     * The incoming goods of the product's items (each a quantity and its expected date), through
     * InventoryService, in one transaction. Items left out are not changed.
     *
     * @param  Product  $product  a parent (not a variation), from WholesaleProductRepository::forPriceEdit
     * @param  array<int|string, array{quantity: int|string, expected_at?: ?string, loaded_quantity: int|string, loaded_expected_at?: ?string}>  $incoming  each row with what the page showed
     *
     * @throws ValidationException on `incoming` when an item is not this product's, or on `incoming.<id>.quantity`
     */
    public function saveIncoming(Product $product, array $incoming, ?int $userId): void
    {
        $items = ($product->variations->isEmpty() ? collect([$product]) : $product->variations)->keyBy('id');

        foreach (array_keys($incoming) as $itemId) {
            if (! $items->has((int) $itemId)) {
                throw ValidationException::withMessages(['incoming' => __('These goods belong to another product.')]);
            }
        }

        DB::transaction(function () use ($items, $incoming, $userId): void {
            foreach ($incoming as $itemId => $row) {
                try {
                    $this->inventory->setIncoming($items->get((int) $itemId), (int) $row['quantity'], $row['expected_at'] ?? null, $userId, [
                        'quantity' => (int) $row['loaded_quantity'],
                        'expected_at' => $row['loaded_expected_at'] ?? null,
                    ]);
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(["incoming.{$itemId}.quantity" => collect($e->errors())->flatten()->first()]);
                }
            }
        });
    }
}
