<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A store on the platform. The id is the store slug (its subdomain).
 *
 * @property string $id
 * @property array<string, string>|null $name ['ar' => ..., 'en' => ...]
 * @property string|null $owner_name
 * @property string|null $owner_email
 * @property string|null $owner_phone
 * @property string|null $business_type
 * @property string $status
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected $casts = [
        'name' => 'array',
    ];

    /**
     * Real columns of the tenants table; every other attribute is stored in `data`.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'owner_name',
            'owner_email',
            'owner_phone',
            'business_type',
            'status',
        ];
    }

    /**
     * The id is the store slug: a string key that is always given, never generated
     * (tenancy.id_generator is null, which stancl would otherwise treat as auto-increment).
     */
    public function getIncrementing()
    {
        return false;
    }

    public function shouldGenerateId(): bool
    {
        return false;
    }

    public function getKeyType()
    {
        return 'string';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function primaryDomain(): ?string
    {
        // A table of stores eager-loads its domains: no query per row.
        if ($this->relationLoaded('domains')) {
            return $this->domains->sortBy('id')->first()?->domain;
        }

        return $this->domains()->orderBy('id')->value('domain');
    }
}
