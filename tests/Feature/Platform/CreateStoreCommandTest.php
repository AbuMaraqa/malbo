<?php

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use Tests\CentralTestCase;

class CreateStoreCommandTest extends CentralTestCase
{
    public function test_creates_store_from_options(): void
    {
        $this->beforeApplicationDestroyed(fn () => $this->destroyTenant('cmd1'));

        $this->artisan('stores:create', [
            'slug' => 'cmd1',
            '--name' => 'متجر',
            '--owner-name' => 'Owner',
            '--owner-email' => 'o@cmd1.ps',
            '--password' => 'secret-pass',
        ])
            ->expectsOutputToContain('http://cmd1.localhost:8000/ar')
            ->expectsOutputToContain('http://cmd1.localhost:8000/login')
            ->assertExitCode(0);

        $this->assertNotNull(Tenant::find('cmd1'));
    }

    public function test_prompts_for_missing_values(): void
    {
        $this->beforeApplicationDestroyed(fn () => $this->destroyTenant('cmd2'));

        $this->artisan('stores:create', ['slug' => 'cmd2'])
            ->expectsQuestion('Store name (Arabic)', 'متجر')
            ->expectsQuestion('Owner full name', 'Owner')
            ->expectsQuestion('Owner email', 'o@cmd2.ps')
            ->expectsQuestion('Owner password', 'secret-pass')
            ->expectsQuestion('Confirm the password', 'secret-pass')
            ->assertExitCode(0);

        $this->assertSame('o@cmd2.ps', Tenant::find('cmd2')?->owner_email);
    }

    public function test_refuses_reserved_slug(): void
    {
        $this->artisan('stores:create', $this->storeOptions('admin'))->assertExitCode(1);

        $this->assertNull(Tenant::find('admin'));
    }

    public function test_refuses_short_password_and_bad_email(): void
    {
        $this->artisan('stores:create', ['--password' => 'short'] + $this->storeOptions('cmd3'))->assertExitCode(1);
        $this->artisan('stores:create', ['--owner-email' => 'not-an-email'] + $this->storeOptions('cmd3'))->assertExitCode(1);

        $this->assertNull(Tenant::find('cmd3'));
    }

    private function storeOptions(string $slug): array
    {
        return [
            'slug' => $slug,
            '--name' => 'متجر',
            '--owner-name' => 'Owner',
            '--owner-email' => "o@{$slug}.ps",
            '--password' => 'secret-pass',
        ];
    }
}
