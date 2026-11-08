<?php

declare(strict_types=1);

namespace App\Platform\Provisioning;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Settings\GeneralSettings;
use Modules\Store\Services\StoreAppearanceService;

/**
 * Fills a freshly provisioned store. Runs inside the store (tenancy initialized).
 */
class SetUpNewStore
{
    public function handle(NewStoreData $data): User
    {
        // Login lowers its input and PostgreSQL compares exactly: a capital here could never sign in.
        $email = Str::lower(trim($data->ownerEmail));

        $owner = User::create([
            'name' => $data->ownerName,
            'email' => $email,
            'phone' => $data->ownerPhone,
            'password' => Hash::make($data->password),
        ]);
        $owner->assignRole(UserRole::SUPER_ADMIN->value);

        $settings = app(GeneralSettings::class);
        $settings->site_name = $data->nameAr;
        $settings->email_address_for_contact = $data->contactEmail ?? $email;
        $settings->save();

        $this->applyBranding($data);

        return $owner;
    }

    private function applyBranding(NewStoreData $data): void
    {
        $appearance = array_filter(
            ['primary_color' => $data->primaryColor, 'font' => $data->font],
            fn (?string $value) => filled($value),
        );

        if ($appearance !== [] || $data->logo !== null) {
            app(StoreAppearanceService::class)->save($appearance, [], ['logo' => $data->logo]);
        }
    }
}
