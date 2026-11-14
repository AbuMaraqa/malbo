<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * A contact that asked for no more marketing messages (cart reminders, back-in-stock alerts):
 * `email` with the lowercased address, or `phone` with its national digits (Phone::normalize).
 */
class MarketingOptOut extends Model
{
    public const EMAIL = 'email';

    public const PHONE = 'phone';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'marketing_opt_outs');
    }

    protected $fillable = [
        'channel',
        'value',
    ];
}
