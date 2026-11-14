<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Modules\Items\Enums\ShipmentStatus;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An order on its way with a courier. ShipmentService is its only writer. `courier` is a key of
 * config/couriers.php.
 */
class Shipment extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix').'shipments');
    }

    protected $fillable = [
        'order_id',
        'courier',
        'tracking_number',
        'status',
        'cost',
        'notes',
        'shipped_at',
        'delivered_at',
        'created_by',
    ];

    protected $casts = [
        'status' => ShipmentStatus::class,
        'cost' => 'decimal:2',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['courier', 'tracking_number', 'status', 'cost', 'notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName(class_basename($this));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Every status the shipment reached, oldest first.
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('id');
    }
}
