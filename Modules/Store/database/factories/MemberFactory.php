<?php

namespace Modules\Store\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Store\Entities\Member;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'phone' => '0599'.$this->faker->unique()->numerify('######'),
            'email' => null,
            'locale' => 'ar',
        ];
    }
}
