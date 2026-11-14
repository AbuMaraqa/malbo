<?php

namespace Tests\Feature\Store\Concerns;

use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Modules\Store\Settings\MarketingSettings;

/**
 * Marketing settings for a test, and a shirt with sized variants.
 */
trait MakesMarketing
{
    protected function marketing(array $values = []): MarketingSettings
    {
        $settings = app(MarketingSettings::class);

        foreach ([
            'pixel_enabled' => true,
            'meta_pixel_id' => '1234567890',
            'capi_enabled' => true,
            'meta_capi_token' => 'TEST-TOKEN',
            'meta_test_event_code' => null,
            ...$values,
        ] as $name => $value) {
            $settings->{$name} = $value;
        }

        $settings->save();

        return $settings;
    }

    protected function sizeAttribute(): Attribute
    {
        return Attribute::factory()->create(['slug' => 'size', 'name' => 'المقاس', 'type' => AttributeType::SELECT->value, 'is_filterable' => true, 'is_size' => true]);
    }

    protected function value(Attribute $attribute, string $name, int $order = 0): AttributeValue
    {
        return AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'name' => $name, 'sort_order' => $order, 'is_active' => true]);
    }

    /**
     * A variant of $parent with these values, its stock managed.
     *
     * @param  list<AttributeValue>  $values
     */
    protected function variantOf(Product $parent, array $values, array $columns = []): Product
    {
        $variant = Product::factory()->variationOf($parent)->create(['manage_stock' => true, 'stock_quantity' => 5, ...$columns]);
        $variant->attributeValues()->attach(array_map(fn (AttributeValue $value) => $value->id, $values));
        $parent->touch();

        return $variant;
    }
}
