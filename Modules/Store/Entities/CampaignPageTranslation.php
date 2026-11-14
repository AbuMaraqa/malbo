<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;

class CampaignPageTranslation extends Model
{
    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'campaign_page_translations');
    }

    protected $fillable = [
        'title',
        'seo_title',
        'seo_description',
    ];
}
