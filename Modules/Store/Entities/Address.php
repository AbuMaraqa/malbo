<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\District;

/**
 * A delivery address a buyer saved in their account. AddressService writes it.
 */
class Address extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'addresses');
    }

    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'phone',
        'city_id',
        'district_id',
        'address_line',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
