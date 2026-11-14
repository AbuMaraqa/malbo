<?php

namespace Tests\Feature\Items\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductStatus;

/**
 * A Super Admin and the admin product form around them, as the browser posts it.
 */
trait EditsProducts
{
    protected User $staff;

    protected Category $category;

    protected function setUpProductForm(): void
    {
        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->staff = User::forceCreate([
            'name' => 'Staff',
            'email' => 'staff-'.Str::random(10).'@items.test',
            'password' => 'secret-password',
        ])->assignRole('Super Admin');

        $this->category = Category::factory()->create();
    }

    protected function productFields(array $overrides = []): array
    {
        return [
            'name' => 'منتج',
            'slug' => 'product-'.Str::lower(Str::random(8)),
            'status' => ProductStatus::PUBLISHED->value,
            'base_price' => 100,
            'category_id' => [$this->category->id],
            'is_featured' => false,
            'is_digital' => false,
            ...$overrides,
        ];
    }

    protected function variationRow(array $overrides = []): array
    {
        return [
            'name' => 'متغير '.Str::random(4),
            'base_price' => 100,
            'category_id' => [$this->category->id],
            'is_featured' => false,
            'is_digital' => false,
            ...$overrides,
        ];
    }

    protected function storeProduct(array $overrides = [], string $locale = 'ar'): TestResponse
    {
        return $this->actingAs($this->staff)->post($this->adminUrl('/products', $locale), [
            ...$this->productFields($overrides),
            'image' => UploadedFile::fake()->image('product.jpg'),
        ]);
    }

    protected function updateProduct(Product $product, array $overrides = [], string $locale = 'ar'): TestResponse
    {
        return $this->actingAs($this->staff)->put($this->adminUrl('/products/'.$product->id, $locale), $this->productFields([
            'slug' => $product->slug,
            'base_price' => $product->base_price,
            'status' => $product->status->value,
            ...$overrides,
        ]));
    }

    protected function adminPage(string $path, string $locale = 'ar'): TestResponse
    {
        return $this->actingAs($this->staff)->get($this->adminUrl($path, $locale));
    }

    protected function adminUrl(string $path, string $locale = 'ar'): string
    {
        return $this->siteUrl('/'.$locale.'/admin'.$path);
    }

    protected function latestParentProduct(): Product
    {
        return Product::query()->with('translations')->whereNull('parent_id')->latest('id')->firstOrFail();
    }
}
