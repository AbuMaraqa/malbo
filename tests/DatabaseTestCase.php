<?php

namespace Tests;

use Database\Seeders\BaseDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests that use the database (`core_view_testing`). It is migrated and seeded with the base data
 * (permissions, roles, settings; no user) once per run, and every test runs inside a transaction
 * that is rolled back afterwards.
 */
abstract class DatabaseTestCase extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = BaseDataSeeder::class;

    /**
     * An absolute URL on the site, as a browser would request it.
     */
    protected function siteUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }
}
