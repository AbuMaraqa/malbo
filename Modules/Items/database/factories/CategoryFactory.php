<?php

namespace Modules\Items\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\CategoryStatus;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => 'قسم '.Str::random(5),
            'slug' => 'category-'.Str::lower(Str::random(8)),
            'status' => CategoryStatus::ACTIVE,
            'sort_order' => 0,
        ];
    }
}
