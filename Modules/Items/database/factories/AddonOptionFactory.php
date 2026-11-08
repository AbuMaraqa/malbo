<?php

namespace Modules\Items\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;

/**
 * @extends Factory<AddonOption>
 */
class AddonOptionFactory extends Factory
{
    protected $model = AddonOption::class;

    public function definition(): array
    {
        return [
            'name' => 'خيار '.Str::random(5),
            'addon_id' => Addon::factory(),
            'price' => 5,
            'is_default' => 0,
            'status' => 1,
            'is_quantifiable' => 0,
        ];
    }
}
