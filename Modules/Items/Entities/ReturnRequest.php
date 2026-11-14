<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Modules\Items\Enums\ReturnReason;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Enums\ReturnRequestType;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A buyer's (or a staff member's) request to return or exchange items of a delivered order.
 * ReturnRequestService is its only writer. `staff_note` is for staff: the buyer never sees it.
 */
class ReturnRequest extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix').'return_requests');
    }

    protected $fillable = [
        'order_id',
        'user_id',
        'type',
        'status',
        'reason',
        'customer_note',
        'staff_note',
        'refund_amount',
        'refunded',
        'refunded_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'type' => ReturnRequestType::class,
        'status' => ReturnRequestStatus::class,
        'reason' => ReturnReason::class,
        'refunded' => 'boolean',
        'refunded_at' => 'datetime',
        'refund_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'staff_note', 'refunded', 'refund_amount'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName(class_basename($this));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReturnRequestLine::class)->orderBy('id');
    }

    /**
     * Every status the request reached, oldest first.
     */
    public function history(): HasMany
    {
        return $this->hasMany(ReturnRequestHistory::class)->orderBy('id');
    }
}
