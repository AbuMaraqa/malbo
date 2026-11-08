<?php

namespace Modules\Items\Services;

use Illuminate\Support\Facades\DB;
use Modules\Items\Entities\Product;

/**
 * A product's addons: the addon_product pivot (rules) and addon_product_option (per-option price
 * and default overrides). Called inside ProductService's transaction.
 */
class ProductAddons
{
    /**
     * Replaces every addon of $product with $addons (the form's repeater items).
     *
     * @param  array<int, array<string, mixed>>  $addons
     */
    public function replace(Product $product, array $addons, int $userId): void
    {
        $pivots = DB::table($this->table('addon_product'));
        $oldIds = (clone $pivots)->where('product_id', $product->id)->pluck('id');

        if ($oldIds->isNotEmpty()) {
            DB::table($this->table('addon_product_option'))->whereIn('addon_product_id', $oldIds)->delete();
        }
        (clone $pivots)->where('product_id', $product->id)->delete();

        foreach ($addons as $addon) {
            if (empty($addon['addon_id'])) {
                continue;
            }

            $pivotId = DB::table($this->table('addon_product'))->insertGetId([
                'addon_id' => $addon['addon_id'],
                'product_id' => $product->id,
                'is_required' => $addon['is_required'] ?? false,
                'min_selection' => $addon['min_selection'] ?? 1,
                'max_selection' => $addon['max_selection'] ?? null,
                'max_quantity_per_option' => $addon['max_quantity_per_option'] ?? 1,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $overrides = [];
            foreach ($addon['allowed_options'] ?? [] as $option) {
                if (empty($option['addon_option_id'])) {
                    continue;
                }

                $overrides[] = [
                    'addon_product_id' => $pivotId,
                    'addon_option_id' => $option['addon_option_id'],
                    'price' => $option['price'] ?? null,
                    'is_default' => $option['is_default'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($overrides !== []) {
                DB::table($this->table('addon_product_option'))->insert($overrides);
            }
        }
    }

    private function table(string $name): string
    {
        return config('items.table_prefix').$name;
    }
}
