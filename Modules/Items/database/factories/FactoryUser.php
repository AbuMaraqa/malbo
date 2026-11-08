<?php

namespace Modules\Items\database\factories;

use Illuminate\Support\Str;
use Modules\Core\Entities\User;

/**
 * The user factories put in `created_by` columns that are NOT NULL. There is no usable
 * UserFactory (Database\Factories\UserFactory targets a model that does not exist).
 */
class FactoryUser
{
    public static function id(): int
    {
        return (int) (User::query()->value('id') ?? User::forceCreate([
            'name' => 'Factory user',
            'email' => 'factory-'.Str::lower(Str::random(10)).'@factories.test',
            'password' => 'password',
        ])->id);
    }
}
