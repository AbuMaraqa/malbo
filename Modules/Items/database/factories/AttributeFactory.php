<?php

namespace Modules\Items\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Items\Entities\Attribute;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    public function definition(): array
    {
        return [
            'name' => 'خاصية '.Str::random(5),
            'slug' => 'attribute-'.Str::lower(Str::random(8)),
            'type' => 1,
        ];
    }
}
