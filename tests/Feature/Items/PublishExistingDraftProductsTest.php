<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductStatus;
use Tests\DatabaseTestCase;

/**
 * Status never had a form field, so every existing product is a draft by accident (spec §6.3).
 */
class PublishExistingDraftProductsTest extends DatabaseTestCase
{
    private const UPDATED_AT = '2025-01-01 10:00:00';

    public function test_backfill_publishes_drafts_without_touching_updated_at(): void
    {
        $parent = $this->product(ProductStatus::DRAFT);
        $variation = $this->product(ProductStatus::DRAFT, ['parent_id' => $parent->id]);
        $trashed = $this->product(ProductStatus::DRAFT, ['deleted_at' => '2025-02-01 00:00:00']);
        $archived = $this->product(ProductStatus::ARCHIVED);

        (require module_path('Items', 'database/migrations/2026_10_26_100001_publish_existing_draft_products.php'))->up();

        foreach ([$parent, $variation, $trashed] as $product) {
            $row = $this->row($product);
            $this->assertSame(ProductStatus::PUBLISHED->value, (int) $row->status, "product {$product->id}");
            $this->assertSame(self::UPDATED_AT, $row->updated_at, "product {$product->id}");
        }

        $this->assertSame(ProductStatus::ARCHIVED->value, (int) $this->row($archived)->status);
    }

    private function product(ProductStatus $status, array $columns = []): Product
    {
        $product = Product::create(['name' => 'منتج', 'slug' => 'p-'.Str::random(8), 'base_price' => 10]);

        // As stored before this change: straight in the table, with an old updated_at.
        $this->table()->where('id', $product->id)->update([
            'status' => $status->value,
            'updated_at' => self::UPDATED_AT,
            ...$columns,
        ]);

        return $product;
    }

    private function row(Product $product): object
    {
        return $this->table()->where('id', $product->id)->first();
    }

    private function table(): \Illuminate\Database\Query\Builder
    {
        return DB::table(config('items.table_prefix').'products');
    }
}
