<?php

namespace Modules\Store\Entities;

use Modules\Store\database\factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * A message a buyer sent from the storefront's contact page. Visitors create these, so there
 * is no `created_by`.
 */
class ContactMessage extends Model
{
    protected static function newFactory(): ContactMessageFactory
    {
        return ContactMessageFactory::new();
    }

    use HasFactory;
    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('store.table_prefix') . 'contact_messages');
    }

    protected $fillable = [
        'name',
        'phone',
        'email',
        'subject',
        'message',
        'locale',
        'ip',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
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

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
