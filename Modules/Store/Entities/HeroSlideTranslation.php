<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;

class HeroSlideTranslation extends Model
{
    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'hero_slide_translations');
    }

    protected $fillable = [
        'eyebrow',
        'title',
        'subtitle',
        'button_label',
    ];
}
