<?php

namespace Tests\Feature\Items\Concerns;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\Currency;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Entities\Governorate;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;

/**
 * A staff member, a customer and a delivery place, and the admin order form around them.
 */
trait MakesOrders
{
    protected User $staff;

    protected User $customer;

    protected Branch $branch;

    protected City $city;

    protected District $district;

    protected function setUpOrders(): void
    {
        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->staff = User::forceCreate([
            'name' => 'Staff',
            'email' => 'staff-'.Str::random(10).'@orders.test',
            'password' => 'secret-password',
        ])->assignRole('Super Admin');

        $this->customer = User::forceCreate([
            'name' => 'Ahmad Customer',
            'email' => 'customer-'.Str::random(10).'@orders.test',
            'password' => 'secret-password',
        ]);

        $this->branch = Branch::factory()->create();

        $currency = Currency::query()->firstOrCreate(['code' => 'ILS'], ['name' => 'شيكل', 'symbol' => '₪']);
        $country = Country::query()->firstOrCreate(['code' => 'ZZ'], ['name' => 'بلد', 'phone_code' => '970', 'currency_id' => $currency->id]);
        $governorate = Governorate::query()->create(['name' => 'محافظة', 'country_id' => $country->id]);
        $this->city = City::query()->create(['name' => 'مدينة', 'governorate_id' => $governorate->id]);
        $this->district = District::query()->create(['name' => 'حي', 'city_id' => $this->city->id]);
    }

    /**
     * A product with `$stock` units, its stock managed.
     */
    protected function stocked(int $stock, array $attributes = []): Product
    {
        return Product::factory()->create([
            'base_price' => 100,
            'sale_price' => null,
            'manage_stock' => true,
            'stock_quantity' => $stock,
            'low_stock_threshold' => 0,
            ...$attributes,
        ]);
    }

    /**
     * A shirt (names in Arabic and English) with one variant: Color = Black, Size = L.
     *
     * @return array{0: Product, 1: Product}
     */
    protected function shirtWithVariant(int $stock = 10): array
    {
        $shirt = Product::factory()->create(['name' => 'قميص', 'base_price' => 100]);
        $shirt->translateOrNew('en')->name = 'Shirt';
        $shirt->save();

        $variant = Product::factory()->variationOf($shirt)->create([
            'name' => 'قميص أسود L',
            'sku' => 'SHIRT-BLK-L',
            'base_price' => 100,
            'manage_stock' => true,
            'stock_quantity' => $stock,
            'low_stock_threshold' => 0,
        ]);

        foreach ([['اللون', 'Color', 'أسود', 'Black'], ['المقاس', 'Size', 'كبير', 'L']] as [$arName, $enName, $arValue, $enValue]) {
            $attribute = Attribute::factory()->create(['name' => $arName]);
            $attribute->translateOrNew('en')->name = $enName;
            $attribute->save();

            $value = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'name' => $arValue]);
            $value->translateOrNew('en')->name = $enValue;
            $value->save();

            $variant->attributeValues()->attach($value->id);
        }

        return [$shirt, $variant];
    }

    /**
     * The admin order form's fields.
     *
     * @param  list<array<string, mixed>>  $items
     */
    protected function orderFields(array $items, array $overrides = []): array
    {
        return [
            'user_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'items' => $items,
            'delivery_fee' => 0,
            'status' => OrderStatus::PENDING->value,
            'payment_method' => PaymentMethod::CASH->value,
            'payment_status' => PaymentStatus::UNPAID->value,
            'delivery_type' => DeliveryType::DELIVERY->value,
            'delivery_address' => 'عنوان',
            'contact_phone' => '0599000000',
            ...$overrides,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function storeOrder(array $items, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->staff)->post(
            $this->siteUrl('/ar/admin/orders'),
            $this->orderFields($items, ['submit_token' => (string) Str::uuid(), ...$overrides]),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function updateOrder(Order $order, array $items, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->staff)->put(
            $this->siteUrl('/ar/admin/orders/'.$order->id),
            $this->orderFields($items, ['status' => $order->status->value, ...$overrides]),
        );
    }

    protected function lastOrder(): Order
    {
        return Order::query()->latest('id')->firstOrFail();
    }
}
