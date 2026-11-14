<?php

namespace Modules\Items\Services;

use Illuminate\Support\Facades\DB;
use Modules\Items\Entities\Product;

/**
 * Writes "complete the look": the products shown next to a product on its page, in the order given.
 */
class LookService
{
    /**
     * @param  list<int>  $productIds  in their order; an empty list removes them all
     */
    public function sync(Product $product, array $productIds): void
    {
        $rows = [];

        foreach (array_values(array_unique(array_map('intval', $productIds))) as $index => $id) {
            $rows[$id] = ['sort_order' => $index];
        }

        DB::transaction(fn () => $product->looks()->sync($rows));
    }
}
