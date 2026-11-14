<?php

namespace Tests\Feature\Items\Concerns;

use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\BundleComponent;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\BundleType;

/**
 * Fashion products with sizes, and sets or bundles made of them.
 */
trait MakesBundles
{
    private ?Attribute $sizeAttribute = null;

    /**
     * A product with one variant per size, each with its stock (null: stock not managed).
     *
     * @param  array<string, ?int>  $sizes  size => stock
     * @return array{0: Product, 1: array<string, Product>}
     */
    protected function sized(string $name, array $sizes, float $price = 100, array $attributes = []): array
    {
        $this->sizeAttribute ??= Attribute::factory()->create(['name' => 'المقاس', 'type' => 1]);

        $parent = Product::factory()->create(['name' => $name, 'base_price' => $price, ...$attributes]);
        $variants = [];

        foreach ($sizes as $size => $stock) {
            $variant = Product::factory()->variationOf($parent)->create([
                'name' => $name.' '.$size,
                'sku' => strtoupper(substr(md5($name), 0, 4)).'-'.$size,
                'base_price' => $price,
                'manage_stock' => $stock !== null,
                'stock_quantity' => (int) $stock,
                'stock_status' => $stock === 0 ? 'out_of_stock' : 'in_stock',
            ]);
            $value = AttributeValue::query()->where('attribute_id', $this->sizeAttribute->id)->whereTranslation('name', (string) $size)->first()
                ?? AttributeValue::factory()->create(['attribute_id' => $this->sizeAttribute->id, 'name' => (string) $size]);
            $variant->attributeValues()->attach($value->id);
            $variants[(string) $size] = $variant;
        }

        return [$parent, $variants];
    }

    /**
     * A set (or bundle) of these components: [product, quantity] pairs.
     *
     * @param  list<array{0: Product, 1: int}>  $components
     */
    protected function bundle(array $components, array $attributes = [], BundleType $type = BundleType::SET): Product
    {
        $bundle = Product::factory()->create([
            'name' => 'طقم',
            'slug' => 'set-'.uniqid(),
            'base_price' => 300,
            'stock_status' => 'out_of_stock',
            'bundle_type' => $type->value,
            ...$attributes,
        ]);

        foreach ($components as $index => [$product, $quantity]) {
            BundleComponent::query()->create(['bundle_id' => $bundle->id, 'product_id' => $product->id, 'quantity' => $quantity, 'sort_order' => $index]);
        }

        return $bundle;
    }
}
