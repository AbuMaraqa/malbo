<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\User;
use Modules\Items\Enums\ShipmentStatus;

/**
 * One status a shipment reached, when, with an optional note. Written by ShipmentService only.
 */
class ShipmentEvent extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix').'shipment_events');
    }

    protected $fillable = [
        'shipment_id',
        'status',
        'note',
        'at',
        'created_by',
    ];

    protected $casts = [
        'status' => ShipmentStatus::class,
        'at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
