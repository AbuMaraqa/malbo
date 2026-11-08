<?php

declare(strict_types=1);

namespace App\Platform;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the platform dashboard reads and does with stores.
 */
class StoreService
{
    public function tableQuery(): Builder
    {
        return Tenant::query()->with('domains');
    }

    /**
     * @return array{total: int, active: int, suspended: int, createdThisMonth: int}
     */
    public function counts(): array
    {
        return [
            'total' => Tenant::count(),
            'active' => Tenant::where('status', Tenant::STATUS_ACTIVE)->count(),
            'suspended' => Tenant::where('status', Tenant::STATUS_SUSPENDED)->count(),
            'createdThisMonth' => Tenant::where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    public function setStatus(Tenant $store, string $status): void
    {
        $store->update(['status' => $status]);
    }

    /**
     * TenantDeleted drops the store's database and archives its files; domains cascade.
     */
    public function delete(Tenant $store): void
    {
        $store->delete();
    }

    /**
     * A single-use sign-in link into the store's admin as its owner, or null when it has none.
     * Enters the store's database: never call it per table row.
     */
    public function adminSignInUrl(Tenant $store, string $locale): ?string
    {
        $userId = StoreOwner::userId($store);

        return $userId === null ? null : StoreUrl::impersonation($store, $userId, $locale);
    }

    public static function name(Tenant $store): string
    {
        $name = $store->name ?? [];

        return $name[app()->getLocale()] ?? $name['ar'] ?? $name['en'] ?? $store->id;
    }
}
