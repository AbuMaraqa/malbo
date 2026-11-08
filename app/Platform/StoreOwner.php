<?php

declare(strict_types=1);

namespace App\Platform;

use App\Models\Tenant;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;

/**
 * Whom the platform signs in as when it opens a store's back office.
 */
class StoreOwner
{
    /**
     * The owner recorded at creation; for imported stores (no recorded owner) the first
     * Super Admin of the store. Null when the store has neither.
     */
    public static function userId(Tenant $tenant): ?int
    {
        $recorded = $tenant->owner_user_id;

        return $tenant->run(function () use ($recorded) {
            if ($recorded !== null && User::whereKey($recorded)->exists()) {
                return (int) $recorded;
            }

            $first = User::role(UserRole::SUPER_ADMIN->value)->orderBy('id')->value('id');

            return $first === null ? null : (int) $first;
        });
    }
}
