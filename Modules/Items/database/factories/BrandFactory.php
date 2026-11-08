<?php

namespace Modules\Items\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Items\Entities\Brand;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'name' => 'علامة تجارية '.Str::random(5),
            'slug' => 'brand-'.Str::lower(Str::random(8)),
            'created_by' => fn () => FactoryUser::id(),
        ];
    }
}
