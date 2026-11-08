<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Someone who runs the platform (guard `platform`), stored in the central database.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 */
class PlatformAdmin extends Authenticatable
{
    use CentralConnection;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
