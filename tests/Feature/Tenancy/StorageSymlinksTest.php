<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\Jobs\CreateStorageSymlinks;
use App\Tenancy\Jobs\RemoveStorageSymlinks;
use App\Tenancy\StorageLink;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TenantTestCase;

class StorageSymlinksTest extends TenantTestCase
{
    private string $link;

    private bool $linkExisted;

    protected function setUp(): void
    {
        parent::setUp();

        $this->link = public_path('public-testing');
        $this->linkExisted = StorageLink::isLink($this->link);
    }

    public function test_links_the_tenant_public_disk_under_its_url_prefix_and_removes_it(): void
    {
        (new RemoveStorageSymlinks($this->tenant))->handle();
        $this->assertFalse(StorageLink::isLink($this->link), 'The link must be gone before the test.');

        (new CreateStorageSymlinks($this->tenant))->handle();

        $this->assertTrue(StorageLink::isLink($this->link), 'public/public-testing must be a symlink (or junction).');
        Storage::disk('public')->put('link-probe.txt', 'through the link');
        $this->assertSame('through the link', file_get_contents($this->link.'/link-probe.txt'));
        // The one link also serves the disks under app/public (items, offers, media, ...).
        Storage::disk('offers')->put('link-probe.txt', 'offer through the link');
        $this->assertSame('offer through the link', file_get_contents($this->link.'/items/offers/link-probe.txt'));

        // Running it again (e.g. a retried pipeline) leaves the link in place.
        (new CreateStorageSymlinks($this->tenant))->handle();
        $this->assertTrue(StorageLink::isLink($this->link));

        (new RemoveStorageSymlinks($this->tenant))->handle();
        clearstatcache();
        $this->assertFalse(StorageLink::isLink($this->link));
        $this->assertFalse(file_exists($this->link));
        $this->assertTrue(Storage::disk('public')->exists('link-probe.txt'), 'Removing the link must keep the files.');
    }

    public function test_a_link_that_cannot_be_created_is_reported_without_failing(): void
    {
        Exceptions::fake();
        (new RemoveStorageSymlinks($this->tenant))->handle();

        $this->app->instance('files', new class extends Filesystem
        {
            public function link($target, $link)
            {
                throw new RuntimeException('symlink(): Permission denied');
            }
        });

        (new CreateStorageSymlinks($this->tenant))->handle(); // must not throw

        $this->assertFalse(StorageLink::isLink($this->link));
        Exceptions::assertReported(fn (RuntimeException $e) => str_contains($e->getMessage(), 'public-testing'));
    }

    public function test_an_existing_directory_at_the_link_path_is_left_alone_and_reported(): void
    {
        Exceptions::fake();
        (new RemoveStorageSymlinks($this->tenant))->handle();
        mkdir($this->link);
        file_put_contents($this->link.'/keep.txt', 'mine');

        try {
            (new CreateStorageSymlinks($this->tenant))->handle();
            (new RemoveStorageSymlinks($this->tenant))->handle();

            $this->assertSame('mine', file_get_contents($this->link.'/keep.txt'));
            Exceptions::assertReported(fn (RuntimeException $e) => str_contains($e->getMessage(), 'not a link'));
        } finally {
            unlink($this->link.'/keep.txt');
            rmdir($this->link);
        }
    }

    public function test_stores_link_repairs_the_link_of_one_store(): void
    {
        (new RemoveStorageSymlinks($this->tenant))->handle();

        $this->artisan('stores:link', ['id' => 'testing'])
            ->expectsOutputToContain('Store [testing]: linked')
            ->assertSuccessful();

        $this->assertTrue(StorageLink::isLink($this->link));
    }

    public function test_stores_link_without_an_id_links_every_store(): void
    {
        (new RemoveStorageSymlinks($this->tenant))->handle();

        $this->artisan('stores:link')
            ->expectsOutputToContain('Store [testing]: linked')
            ->assertSuccessful();

        $this->assertTrue(StorageLink::isLink($this->link));
    }

    public function test_stores_link_fails_for_an_unknown_store(): void
    {
        $this->artisan('stores:link', ['id' => 'no-such-store'])
            ->expectsOutputToContain('Store [no-such-store] not found.')
            ->assertFailed();
    }

    public function test_stores_link_fails_when_a_link_cannot_be_created(): void
    {
        Exceptions::fake();
        (new RemoveStorageSymlinks($this->tenant))->handle();
        $this->app->instance('files', new class extends Filesystem
        {
            public function link($target, $link)
            {
                throw new RuntimeException('symlink(): Permission denied');
            }
        });

        $this->artisan('stores:link', ['id' => 'testing'])
            ->expectsOutputToContain('Store [testing]: could not link')
            ->assertFailed();
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            Storage::disk('public')->delete('link-probe.txt');
            Storage::disk('offers')->delete('link-probe.txt');

            $this->app->forgetInstance('files');
            (new RemoveStorageSymlinks($this->tenant))->handle();
            if ($this->linkExisted) {
                (new CreateStorageSymlinks($this->tenant))->handle();
            }
        }

        parent::tearDown();
    }
}
