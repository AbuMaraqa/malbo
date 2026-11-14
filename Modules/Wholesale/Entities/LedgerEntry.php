<?php

namespace Modules\Wholesale\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\ReturnRequest;
use Modules\Wholesale\Enums\LedgerEntryType;
use Modules\Wholesale\Enums\LedgerPaymentMethod;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One entry of a merchant's account (MerchantLedger writes them; never changed afterwards). The
 * amount is signed: + the merchant owes more, − a payment or a credit.
 */
class LedgerEntry extends Model
{
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('wholesale.table_prefix').'ledger_entries');
    }

    protected $fillable = [
        'merchant_id',
        'type',
        'amount',
        'order_id',
        'return_request_id',
        'method',
        'reference',
        'note',
        'receipt_path',
        'receipt_name',
        'entry_date',
        'created_by',
        'source',
    ];

    protected $casts = [
        'type' => LedgerEntryType::class,
        'method' => LedgerPaymentMethod::class,
        'amount' => 'decimal:2',
        'entry_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['merchant_id', 'type', 'amount', 'order_id', 'return_request_id', 'method', 'reference', 'note', 'entry_date'])
            ->dontLogEmptyChanges()
            ->useLogName(class_basename($this));
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
