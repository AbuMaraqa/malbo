<?php

namespace Modules\Wholesale\Services;

use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\Product;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Repositories\Contracts\WholesaleProductRepositoryInterface;
use Modules\Wholesale\Support\WholesaleItems;

/**
 * Puts a past wholesale order back into the merchant's cart, a product at a time through
 * WholesaleCartService::set (so every cart rule holds), at today's prices and availability. Lines
 * already in the cart are added to; a line that would pass what can be ordered is lowered to it.
 * Skipped, each with its reason: a line whose product is gone or unavailable, not sold wholesale to
 * this merchant, or (a product sold in size runs) a color whose quantities no longer make whole
 * runs. Nothing is ordered: the merchant reviews the cart and checks out.
 */
class ReorderService
{
    public function __construct(
        private readonly WholesaleCartService $cart,
        private readonly WholesaleProductRepositoryInterface $products,
        private readonly WholesalePricing $pricing,
    ) {}

    /**
     * @return array{added: int, skipped: list<string>} lines put in the cart, and why the others were not (or were lowered)
     */
    public function reorder(Merchant $merchant, Order $order): array
    {
        $order->loadMissing(['items' => fn ($items) => $items->orderBy('id'), 'items.product']);

        $groups = [];
        $skipped = [];

        foreach ($order->items as $line) {
            if ($line->product === null) {
                $skipped[] = self::label($line).': '.__('This product is no longer available.');

                continue;
            }

            $groups[(int) ($line->product->parent_id ?: $line->product->id)][] = $line;
        }

        $trees = $this->products->publishedTrees(array_keys($groups));
        $tier = $this->pricing->tierFor($merchant);
        $inCart = WholesaleCartService::quantities($this->cart->current((int) $merchant->user_id));
        $added = 0;

        foreach ($groups as $groupId => $lines) {
            $tree = $trees->get($groupId);

            if ($tree === null) {
                foreach ($lines as $line) {
                    $skipped[] = self::label($line).': '.__('This product is no longer available.');
                }

                continue;
            }

            $items = collect(WholesaleItems::of($tree))->keyBy('id');
            $book = $this->pricing->book($tier, $items->values());
            $orderable = fn (Product $item) => $book->base($item) !== null && WholesaleItems::limit($item) !== 0;

            // The cart's pieces of this product, then the order's on top.
            $wanted = $items->keys()->filter(fn (int $id) => isset($inCart[$id]))->mapWithKeys(fn (int $id) => [$id => $inCart[$id]])->all();
            $fresh = [];

            foreach ($lines as $line) {
                $item = $items->get((int) $line->product_id);
                $why = match (true) {
                    $item === null => __('This product is no longer available.'),
                    $book->base($item) === null => __('This product is not sold wholesale.'),
                    WholesaleItems::limit($item) === 0 => __('Currently unavailable'),
                    default => null,
                };

                if ($why !== null) {
                    $skipped[] = self::label($line).': '.$why;

                    continue;
                }

                $limit = WholesaleItems::limit($item);
                $quantity = ($wanted[$item->id] ?? 0) + (int) $line->quantity;

                if ($limit !== null && $quantity > $limit) {
                    $skipped[] = self::label($line).': '.__('Lowered to the :count that can be ordered.', ['count' => $limit]);
                    $quantity = $limit;
                }

                $wanted[$item->id] = $quantity;
                $fresh[$item->id] = $line;
            }

            if (! ($tree->wholesale_by_piece ?? true)) {
                // A color whose quantities no longer make whole runs keeps what the cart had.
                foreach ($items->groupBy(fn (Product $item) => WholesaleItems::colorKey($item)) as $row) {
                    $rowQuantities = $row->mapWithKeys(fn (Product $item) => [$item->id => $wanted[$item->id] ?? 0])->all();
                    $touched = $row->contains(fn (Product $item) => isset($fresh[$item->id]));

                    if (! $touched || ($problem = WholesaleItems::runProblem($items->values()->all(), $orderable, $rowQuantities)) === null) {
                        continue;
                    }

                    foreach ($row as $item) {
                        if (isset($fresh[$item->id])) {
                            $skipped[] = self::label($fresh[$item->id]).': '.$problem;
                            unset($fresh[$item->id]);
                        }

                        $wanted[$item->id] = $inCart[$item->id] ?? 0;
                    }
                }
            }

            if ($fresh === []) {
                continue;
            }

            try {
                $this->cart->set($merchant, (int) $tree->id, array_filter($wanted));
                $added += count($fresh);
            } catch (ValidationException $e) {
                $reason = (string) collect($e->errors())->flatten()->first();

                foreach ($fresh as $line) {
                    $skipped[] = self::label($line).': '.$reason;
                }
            }
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * The line as it was sold, in the current language: its name and options.
     */
    private static function label(OrderItem $line): string
    {
        $locale = app()->getLocale();
        $text = fn (?array $values) => (string) ($values[$locale] ?? collect($values ?? [])->first(fn ($value) => filled($value)) ?? '');
        $options = collect($line->options ?? [])->map(fn (array $option) => $text($option['value'] ?? null))->filter()->implode(' / ');
        $name = $text($line->product_name) ?: (string) $line->sku;

        return $options !== '' ? $name.' ('.$options.')' : $name;
    }
}
