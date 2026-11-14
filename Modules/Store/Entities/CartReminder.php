<?php

namespace Modules\Store\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One abandoned-cart reminder: its place in the sequence (1, 2), how it went (mail or sms) and to
 * whom. The row is written before the message goes (`sending`), so a second run never sends it
 * again; then `sent` or `failed`.
 */
class CartReminder extends Model
{
    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix').'cart_reminders');
    }

    protected $fillable = [
        'cart_id',
        'sequence',
        'channel',
        'recipient',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
