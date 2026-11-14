<?php

namespace Modules\Items\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Entities\User;
use Modules\Items\Enums\AddonType;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class OrderItem extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     */

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('items.table_prefix') . 'order_items');
    }

    protected $fillable = [
        'id',
        'order_id',
        'product_id',
        'price',
        'quantity',
        'total_price',
        'notes',
        'created_by',
        'sku',
        'product_name',
        'options',
        'preorder_quantity',
        'bundle_product_id',
        'bundle_group',
        'bundle',
    ];

    /**
     * product_name: {locale: name}. options: list<{name: {locale: string}, value: {locale: string}}>.
     * What was sold, whatever happens to the product later.
     */
    protected $casts = [
        'product_name' => 'array',
        'options' => 'array',
        // Pieces of the line reserved from incoming goods (a B2B pre-order); null: none.
        'preorder_quantity' => 'integer',
        // A piece of a set or bundle: the set it was sold in (type, sku, name per locale, how many
        // sets, the set's unit price), shared by its pieces (`bundle_group`). Null otherwise.
        'bundle' => 'array',
        'bundle_product_id' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn(string $eventName) => "This model has been {$eventName} and value ")
            ->useLogName(class_basename($this));
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The set or bundle this piece was sold in, if any.
     */
    public function bundleProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class , 'product_id' , 'id');
    }

    public function addonOptions(): BelongsToMany
    {
        return $this->belongsToMany(AddonOption::class, config('items.table_prefix') . 'addon_option_order_item')
            ->withPivot(['quantity','price']);
    }
}
