<?php

namespace Modules\Wholesale\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\User;
use Modules\Wholesale\Enums\MerchantStatus;

/**
 * One step of a merchant's application: from which status to which, why, and who did it (the
 * merchant themselves when they applied).
 */
class MerchantStatusChange extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('wholesale.table_prefix').'merchant_status_changes');
    }

    protected $fillable = [
        'merchant_id',
        'from_status',
        'to_status',
        'note',
        'created_by',
    ];

    protected $casts = [
        'from_status' => MerchantStatus::class,
        'to_status' => MerchantStatus::class,
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
