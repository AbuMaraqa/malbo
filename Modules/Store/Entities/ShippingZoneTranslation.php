<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;

class ShippingZoneTranslation extends Model
{
    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'shipping_zone_translations');
    }

    protected $fillable = [
        'locale',
        'name',
    ];
}
