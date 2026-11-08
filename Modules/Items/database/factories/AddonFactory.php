<?php

namespace Modules\Items\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Items\Entities\Addon;

/**
 * @extends Factory<Addon>
 */
class AddonFactory extends Factory
{
    protected $model = Addon::class;

    public function definition(): array
    {
        return [
            'created_by' => fn () => FactoryUser::id(),
            'name' => 'إضافة '.Str::random(5),
            'type' => 1,
        ];
    }
}
