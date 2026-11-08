<?php

namespace Tests\Support;

use App\Tenancy\TenantMigrationPaths;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Finder\Finder;

/**
 * Per-process bookkeeping of the testing databases, shared by every test base class.
 */
final class TestingDatabases
{
    /**
     * Stores provisioned once and reused across tests and test runs. CentralTestCase
     * never drops their databases; TenantTestCase re-adopts them by fingerprint.
     */
    public const REUSABLE_TENANTS = ['testing', 'other'];

    private static bool $centralMigrated = false;

    private static ?string $fingerprint = null;

    /** @var array<string, true> reusable tenants checked against the fingerprint in this process */
    private static array $verified = [];

    /**
     * migrate:fresh the central testing database once per process.
     * Callers must have run CreatesTenants::ensureTestingDatabases() first.
     */
    public static function migrateCentralOnce(): void
    {
        if (self::$centralMigrated) {
            return;
        }

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        self::$centralMigrated = true;
    }

    public static function isVerified(string $id): bool
    {
        return isset(self::$verified[$id]);
    }

    public static function markVerified(string $id): void
    {
        self::$verified[$id] = true;
    }

    /**
     * Hash of everything that shapes a freshly provisioned store database: the tenant
     * migrations, the app + module seeders, and the store page starter texts a migration
     * reads. A reused database is only trusted when the fingerprint stored in it matches.
     */
    public static function fingerprint(): string
    {
        return self::$fingerprint ??= self::computeFingerprint();
    }

    private static function computeFingerprint(): string
    {
        $directories = array_filter(
            [
                ...TenantMigrationPaths::all(),
                database_path('seeders'),
                ...glob(base_path('Modules/*/database/seeders'), GLOB_ONLYDIR),
                ...glob(base_path('Modules/*/resources/page-templates'), GLOB_ONLYDIR),
            ],
            'is_dir',
        );

        $files = [];
        foreach (Finder::create()->files()->in($directories) as $file) {
            $relative = str_replace('\\', '/', substr($file->getRealPath(), strlen(base_path())));
            $files[$relative] = md5_file($file->getRealPath());
        }
        ksort($files);

        return hash('sha256', json_encode($files));
    }
}
