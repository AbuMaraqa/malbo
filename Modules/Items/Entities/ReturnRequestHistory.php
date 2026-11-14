<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\User;
use Modules\Items\Enums\ReturnRequestStatus;

/**
 * A status a return request reached, when, by whom, with the staff's note.
 */
class ReturnRequestHistory extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix').'return_request_history');
    }

    protected $fillable = [
        'return_request_id',
        'status',
        'note',
        'created_by',
    ];

    protected $casts = [
        'status' => ReturnRequestStatus::class,
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
