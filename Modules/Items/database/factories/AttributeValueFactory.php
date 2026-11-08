<?php

namespace Modules\Items\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;

/**
 * @extends Factory<AttributeValue>
 */
class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    public function definition(): array
    {
        return [
            'name' => 'قيمة '.Str::random(5),
            'attribute_id' => Attribute::factory(),
            'sort_order' => 0,
            'is_active' => 1,
        ];
    }
}
