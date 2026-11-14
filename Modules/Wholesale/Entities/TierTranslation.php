<?php

namespace Modules\Wholesale\Entities;

use Illuminate\Database\Eloquent\Model;

class TierTranslation extends Model
{
    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('wholesale.table_prefix').'tier_translations');
    }

    protected $fillable = [
        'locale',
        'name',
    ];
}
