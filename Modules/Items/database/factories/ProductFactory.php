<?php

namespace Modules\Items\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductStatus;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => 'منتج '.Str::random(5),
            'slug' => 'product-'.Str::lower(Str::random(8)),
            'base_price' => 100,
            'status' => ProductStatus::PUBLISHED,
            'is_active' => 1,
            'is_featured' => 0,
            'is_digital' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => ProductStatus::PUBLISHED]);
    }

    public function draft(): static
    {
        return $this->state(['status' => ProductStatus::DRAFT]);
    }

    public function variationOf(Product $parent): static
    {
        return $this->state(['parent_id' => $parent->id, 'status' => $parent->status]);
    }
}
