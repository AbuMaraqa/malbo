<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use App\Tenancy\Jobs\ArchiveTenantStorage;
use Illuminate\Support\Facades\File;
use Tests\TenantTestCase;

/**
 * ArchiveTenantStorage on an unsaved store "ghost": no database is created.
 * The pipeline wiring is covered by TenantProvisioningTest.
 */
class TenantStorageArchiveTest extends TenantTestCase
{
    private Tenant $ghost;

    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ghost = (new Tenant)->forceFill(['id' => 'ghost']);
        $this->source = app(FilesystemTenancyBootstrapper::class)->tenantStoragePath($this->ghost);
        $this->cleanUpGhost();
    }

    public function test_a_deleted_store_storage_is_moved_aside(): void
    {
        File::ensureDirectoryExists($this->source.'/app/public/items/1');
        File::put($this->source.'/app/public/items/1/x.jpg', 'ghost-file');

        (new ArchiveTenantStorage($this->ghost))->handle();

        clearstatcache();
        $this->assertDirectoryDoesNotExist($this->source);

        $archived = $this->archived();
        $this->assertCount(1, $archived);
        $this->assertMatchesRegularExpression('/tenantghost-\d{8}_\d{6}$/', $archived[0]);
        $this->assertSame('ghost-file', File::get($archived[0].'/app/public/items/1/x.jpg'));

        // A store created again with the same id would start empty; deleting it again
        // in the same second does not overwrite the first archive.
        File::ensureDirectoryExists($this->source);
        (new ArchiveTenantStorage($this->ghost))->handle();
        $this->assertCount(2, $this->archived());
    }

    public function test_an_imported_store_storage_is_never_moved(): void
    {
        File::ensureDirectoryExists($this->source.'/app/public');
        $this->ghost->setInternal('imported', true);

        (new ArchiveTenantStorage($this->ghost))->handle();

        $this->assertDirectoryExists($this->source.'/app/public');
        $this->assertSame([], $this->archived());
    }

    public function test_a_missing_storage_folder_is_a_no_op(): void
    {
        (new ArchiveTenantStorage($this->ghost))->handle();

        $this->assertDirectoryDoesNotExist($this->source);
        $this->assertSame([], $this->archived());
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            $this->cleanUpGhost();
        }

        parent::tearDown();
    }

    /** @return list<string> */
    private function archived(): array
    {
        $archive = app(FilesystemTenancyBootstrapper::class)->centralStoragePath().'/'.ArchiveTenantStorage::ARCHIVE_DIRECTORY;

        return glob($archive.'/tenantghost-*', GLOB_ONLYDIR) ?: [];
    }

    private function cleanUpGhost(): void
    {
        File::deleteDirectory($this->source);
        $this->deleteArchivedStorage('ghost');
    }
}
