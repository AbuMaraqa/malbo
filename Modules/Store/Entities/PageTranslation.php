<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;

class PageTranslation extends Model
{
    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix') . 'page_translations');
    }

    protected $fillable = [
        'store_page_id',
        'locale',
        'title',
        'body',
        'meta_description',
    ];
}
