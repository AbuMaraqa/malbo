<?php

namespace Modules\Wholesale\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\Wholesale\Enums\MerchantStatus;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The wholesale profile of a user with the Merchant role: the shop, its register, where its
 * application stands, its price tier, its sales rep (a staff member) and its own minimum order
 * (empty: the store's). MerchantApplicationService creates it, MerchantService changes it.
 */
class Merchant extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('wholesale.table_prefix').'merchants');
    }

    protected $fillable = [
        'user_id',
        'shop_name',
        'owner_name',
        'city_id',
        'phone',
        'email',
        'commercial_register',
        'register_document_path',
        'register_document_name',
        'shop_address',
        'status',
        'tier_id',
        'sales_rep_id',
        'minimum_order_value',
        'minimum_order_pieces',
        'approved_at',
        'credit_enabled',
        'credit_limit',
    ];

    protected $attributes = [
        'status' => 'pending',
        'credit_enabled' => false,
    ];

    protected $casts = [
        'status' => MerchantStatus::class,
        'minimum_order_value' => 'decimal:2',
        'minimum_order_pieces' => 'integer',
        'approved_at' => 'datetime',
        'credit_enabled' => 'boolean',
        'credit_limit' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['shop_name', 'owner_name', 'city_id', 'phone', 'commercial_register', 'shop_address', 'status', 'tier_id', 'sales_rep_id', 'minimum_order_value', 'minimum_order_pieces', 'credit_enabled', 'credit_limit'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName(class_basename($this));
    }

    public function isApproved(): bool
    {
        return $this->status === MerchantStatus::APPROVED;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    /**
     * Every status change, newest first.
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(MerchantStatusChange::class)->orderByDesc('id');
    }
}
