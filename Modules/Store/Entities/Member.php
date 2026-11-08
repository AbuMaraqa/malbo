<?php

namespace Modules\Store\Entities;

use Modules\Store\database\factories\MemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * A visitor who joined the store's members list from the home page. Visitors create these,
 * so there is no `created_by`; deleting one frees its phone number.
 */
class Member extends Model
{
    protected static function newFactory(): MemberFactory
    {
        return MemberFactory::new();
    }

    use HasFactory;
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix') . 'members');
    }

    protected $fillable = [
        'name',
        'phone',
        'email',
        'locale',
        'ip',
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

    protected static function booted()
    {
        parent::booted();
    }
}
