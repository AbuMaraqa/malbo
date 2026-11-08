<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Platform\Provisioning\NewStoreData;
use App\Platform\Provisioning\SlugAvailability;
use App\Platform\Provisioning\StoreProvisioner;
use App\Platform\Provisioning\StoreProvisioningFailed;
use App\Platform\StoreUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates a store on the platform with its owner account. Asks for anything missing;
 * the password is asked with a hidden prompt (--password is for scripts and tests).
 */
class CreateStore extends Command
{
    protected $signature = 'stores:create
        {slug : The store id, also its subdomain}
        {--name= : Store name in Arabic}
        {--name-en= : Store name in English (defaults to the Arabic name)}
        {--owner-name= : Owner full name}
        {--owner-email= : Owner email, used to sign in}
        {--owner-phone= : Owner mobile phone}
        {--password= : Owner password (asked when missing)}
        {--business-type= : restaurant, grocery, fashion, electronics, beauty or other}';

    protected $description = 'Create a store with its own database and owner account';

    public function handle(StoreProvisioner $provisioner): int
    {
        $slug = SlugAvailability::normalize((string) $this->argument('slug'));

        if ($reason = SlugAvailability::check($slug)) {
            $this->components->error(SlugAvailability::message($reason));

            return self::FAILURE;
        }

        $values = [
            'name' => $this->option('name') ?: $this->ask('Store name (Arabic)'),
            'owner_name' => $this->option('owner-name') ?: $this->ask('Owner full name'),
            'owner_email' => $this->option('owner-email') ?: $this->ask('Owner email'),
            'password' => $this->option('password') ?: $this->secret('Owner password'),
        ];
        $values['password_confirmation'] = $this->option('password') ?: $this->secret('Confirm the password');

        $validator = Validator::make($values, [
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        try {
            $tenant = $provisioner->provision(new NewStoreData(
                slug: $slug,
                nameAr: $values['name'],
                nameEn: $this->option('name-en') ?: null,
                businessType: $this->option('business-type') ?: null,
                ownerName: $values['owner_name'],
                ownerEmail: $values['owner_email'],
                ownerPhone: $this->option('owner-phone') ?: null,
                password: $values['password'],
                contactEmail: null,
            ));
        } catch (ValidationException $e) {
            $this->components->error(collect($e->errors())->flatten()->first());

            return self::FAILURE;
        } catch (StoreProvisioningFailed $e) {
            $this->components->error($e->getMessage().' ('.$e->getPrevious()?->getMessage().')');

            return self::FAILURE;
        }

        $this->components->info("Store [{$tenant->id}] created.");
        $this->line('  Store: '.StoreUrl::for($tenant, '/ar'));
        $this->line('  Staff login: '.StoreUrl::for($tenant, '/login'));

        return self::SUCCESS;
    }
}
