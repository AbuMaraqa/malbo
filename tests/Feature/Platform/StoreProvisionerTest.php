<?php

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Platform\Provisioning\NewStoreData;
use App\Platform\Provisioning\SetUpNewStore;
use App\Platform\Provisioning\StoreProvisioner;
use App\Platform\Provisioning\StoreProvisioningFailed;
use App\Platform\StoreUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Core\Settings\GeneralSettings;
use RuntimeException;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\CentralTestCase;

class StoreProvisionerTest extends CentralTestCase
{
    public function test_provisions_a_store_with_its_owner(): void
    {
        $this->cleanUp('shop1');

        $tenant = app(StoreProvisioner::class)->provision($this->data('shop1'));

        $this->assertSame('shop1.localhost', $tenant->primaryDomain());
        $this->assertSame('owner@shop1.ps', $tenant->owner_email);
        $this->assertSame(['ar' => 'متجر واحد', 'en' => 'متجر واحد'], $tenant->name);
        $this->assertTrue($tenant->isActive());

        $ownerId = $tenant->owner_user_id;

        $tenant->run(function () use ($ownerId) {
            $owner = User::firstWhere('email', 'owner@shop1.ps');

            $this->assertNotNull($owner);
            $this->assertTrue($owner->hasRole('Super Admin'));
            $this->assertTrue(Hash::check('secret-pass', $owner->password));
            $this->assertSame($owner->id, $ownerId);
            $this->assertSame(1, User::count());
            $this->assertSame('متجر واحد', app(GeneralSettings::class)->site_name);
            $this->assertSame('owner@shop1.ps', app(GeneralSettings::class)->email_address_for_contact);
        });

        $this->assertFalse(tenancy()->initialized);
    }

    public function test_setup_failure_leaves_nothing_behind(): void
    {
        $this->cleanUp('shop2');

        $this->app->bind(SetUpNewStore::class, fn () => new class extends SetUpNewStore
        {
            public function handle(NewStoreData $data): User
            {
                throw new RuntimeException('setup broke');
            }
        });

        try {
            app(StoreProvisioner::class)->provision($this->data('shop2'));
            $this->fail('Provisioning should have failed.');
        } catch (StoreProvisioningFailed $e) {
            $this->assertSame('setup broke', $e->getPrevious()?->getMessage());
        }

        $this->assertNull(Tenant::find('shop2'));
        $this->assertFalse(Domain::where('domain', 'shop2.localhost')->exists());
        $this->assertFalse($this->databaseExists(config('tenancy.database.prefix').'shop2'));
    }

    public function test_slug_taken_between_check_and_provision(): void
    {
        Tenant::withoutEvents(fn () => Tenant::create(['id' => 'shop3']));

        try {
            app(StoreProvisioner::class)->provision($this->data('shop3'));
            $this->fail('A taken slug should be a validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }

        $this->assertSame(1, Tenant::whereKey('shop3')->count());
    }

    public function test_store_url_keeps_dev_port(): void
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::create(['id' => 'acme']));
        $tenant->domains()->create(['domain' => 'acme.localhost']);

        $this->app->instance('request', Request::create('http://localhost:8000/ar/platform'));
        $this->assertSame('http://acme.localhost:8000/ar', StoreUrl::for($tenant, '/ar'));

        $this->app->instance('request', Request::create('https://localhost/ar/platform'));
        $this->assertSame('https://acme.localhost/ar', StoreUrl::for($tenant, '/ar'));
    }

    private function data(string $slug): NewStoreData
    {
        return new NewStoreData(
            slug: $slug,
            nameAr: 'متجر واحد',
            nameEn: null,
            businessType: 'restaurant',
            ownerName: 'Owner',
            ownerEmail: "owner@{$slug}.ps",
            ownerPhone: '0599000000',
            password: 'secret-pass',
            contactEmail: null,
        );
    }

    private function cleanUp(string $id): void
    {
        $this->beforeApplicationDestroyed(fn () => $this->destroyTenant($id));
    }
}
