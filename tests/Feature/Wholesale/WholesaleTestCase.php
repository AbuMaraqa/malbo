<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Entities\Tier;
use Modules\Wholesale\Enums\MerchantStatus;
use Tests\Feature\Store\Concerns\MakesShippingZones;
use Tests\Feature\Store\StorefrontTestCase;

/**
 * Merchants, tiers and a shirt sold in size runs (black and white, S, M and L).
 */
abstract class WholesaleTestCase extends StorefrontTestCase
{
    use MakesShippingZones;

    protected function merchant(MerchantStatus $status = MerchantStatus::APPROVED, array $attributes = []): Merchant
    {
        $user = User::forceCreate([
            'name' => 'Owner',
            'email' => 'merchant-'.Str::lower(Str::random(8)).'@shop.test',
            'phone' => '0599111222',
            'password' => 'Secret-pass-123',
        ])->assignRole(UserRole::MERCHANT->value);

        return Merchant::query()->create([
            'user_id' => $user->id,
            'shop_name' => 'محل الأناقة',
            'owner_name' => 'Owner',
            'phone' => '0599111222',
            'email' => $user->email,
            'commercial_register' => 'CR-1001',
            'shop_address' => 'شارع السوق 5',
            'status' => $status,
            ...$attributes,
        ]);
    }

    protected function tier(string $name, float $discount, bool $default = false): Tier
    {
        if ($default) {
            Tier::query()->where('is_default', true)->update(['is_default' => false]);
        }

        $tier = Tier::query()->create(['discount_percent' => $discount, 'is_default' => $default, 'sort_order' => 0]);
        $tier->translateOrNew('ar')->name = $name;
        $tier->save();

        return $tier;
    }

    /**
     * A shirt (wholesale 50, retail 100) in black and white, sizes S, M and L, 10 of each.
     *
     * @return array{0: Product, 1: array<string, Product>} the shirt, and its variants by "black-S"
     */
    protected function shirt(array $attributes = []): array
    {
        $shirt = Product::factory()->create(['name' => 'قميص جملة', 'slug' => 'shirt-'.Str::lower(Str::random(6)), 'base_price' => 100, 'wholesale_price' => 50, ...$attributes]);

        $color = Attribute::factory()->create(['name' => 'اللون', 'type' => AttributeType::COLOR->value]);
        $size = Attribute::factory()->create(['name' => 'المقاس', 'type' => AttributeType::SELECT->value]);

        $colors = [];
        foreach (['black' => ['أسود', '#000000'], 'white' => ['أبيض', '#ffffff']] as $key => [$name, $code]) {
            $colors[$key] = AttributeValue::factory()->create(['attribute_id' => $color->id, 'name' => $name, 'color_code' => $code, 'sort_order' => count($colors)]);
        }

        $sizes = [];
        foreach (['S', 'M', 'L'] as $index => $name) {
            $sizes[$name] = AttributeValue::factory()->create(['attribute_id' => $size->id, 'name' => $name, 'sort_order' => $index]);
        }

        $variants = [];
        foreach ($colors as $colorKey => $colorValue) {
            foreach ($sizes as $sizeKey => $sizeValue) {
                $variant = Product::factory()->variationOf($shirt)->create([
                    'name' => 'قميص '.$colorKey.' '.$sizeKey,
                    'sku' => 'SH-'.strtoupper($colorKey).'-'.$sizeKey.'-'.Str::random(4),
                    'base_price' => 100,
                    'wholesale_price' => null,
                    'manage_stock' => true,
                    'stock_quantity' => 10,
                    'low_stock_threshold' => 0,
                ]);
                $variant->attributeValues()->attach([$colorValue->id, $sizeValue->id]);
                $variants[$colorKey.'-'.$sizeKey] = $variant;
            }
        }

        return [$shirt, $variants];
    }

    /**
     * A product without variants, sold wholesale.
     */
    protected function single(array $attributes = []): Product
    {
        return Product::factory()->create([
            'name' => 'حزام',
            'slug' => 'belt-'.Str::lower(Str::random(6)),
            'base_price' => 40,
            'wholesale_price' => 20,
            'manage_stock' => true,
            'stock_quantity' => 30,
            'low_stock_threshold' => 0,
            ...$attributes,
        ]);
    }

    /**
     * A date $days from today (Y-m-d): incoming goods are reservable only from today on.
     */
    protected static function day(int $days): string
    {
        return now()->addDays($days)->toDateString();
    }
}
