<?php

declare(strict_types=1);

namespace App\Platform\Provisioning;

use App\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Creates a store: tenant row, database (created, migrated, seeded by TenantCreated),
 * domain, owner account and settings. All or nothing: if any step fails, what was
 * created is removed and StoreProvisioningFailed is thrown.
 */
class StoreProvisioner
{
    public function provision(NewStoreData $data): Tenant
    {
        $slug = SlugAvailability::normalize($data->slug);

        if ($reason = SlugAvailability::check($slug)) {
            throw ValidationException::withMessages(['slug' => SlugAvailability::message($reason)]);
        }

        $originalTenant = tenant();

        try {
            $tenant = Tenant::create([
                'id' => $slug,
                'name' => ['ar' => $data->nameAr, 'en' => $data->nameEn ?: $data->nameAr],
                'owner_name' => $data->ownerName,
                'owner_email' => Str::lower(trim($data->ownerEmail)),
                'owner_phone' => $data->ownerPhone,
                'business_type' => $data->businessType,
                'status' => Tenant::STATUS_ACTIVE,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Created by someone else since the check above.
            throw ValidationException::withMessages(['slug' => SlugAvailability::message('taken')]);
        } catch (Throwable $e) {
            // TenantCreated's provisioning pipeline already removed the tenant and its database.
            throw $this->failed($e);
        }

        try {
            $tenant->domains()->create(['domain' => $slug.'.'.config('tenancy.base_domain')]);

            $owner = $tenant->run(fn () => app(SetUpNewStore::class)->handle($data));

            $tenant->update(['owner_user_id' => $owner->getKey()]);
        } catch (Throwable $e) {
            $this->discard($tenant, $originalTenant);

            throw $this->failed($e);
        }

        return $tenant;
    }

    /**
     * Tenant::run() does not restore the previous context when its callback throws.
     */
    protected function discard(Tenant $tenant, ?Tenant $originalTenant): void
    {
        try {
            if ($originalTenant) {
                tenancy()->initialize($originalTenant);
            } else {
                tenancy()->end();
            }

            $tenant->delete(); // TenantDeleted drops the database; domains cascade
        } catch (Throwable $cleanup) {
            report($cleanup); // never hide why provisioning failed
        }
    }

    protected function failed(Throwable $e): StoreProvisioningFailed
    {
        return new StoreProvisioningFailed(__('The store could not be created. Nothing was kept; please try again.'), previous: $e);
    }
}
