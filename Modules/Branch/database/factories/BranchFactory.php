<?php

namespace Modules\Branch\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Items\database\factories\FactoryUser;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Enums\BranchStatus;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'created_by' => fn () => FactoryUser::id(),
            'name' => 'فرع '.Str::random(5),
            'address' => 'عنوان',
            'phone' => '0599000000',
            'email' => 'branch-'.Str::lower(Str::random(6)).'@store.test',
            'city_id' => fn () => City::query()->value('id'),
            'country_id' => fn () => Country::query()->value('id'),
            'status' => BranchStatus::OPEN,
        ];
    }
}
