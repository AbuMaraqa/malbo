<?php

namespace Tests;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests that use the database (`core_view_testing`). It is migrated and seeded once per run with
 * everything `db:seed` creates (the admin account included), and every test runs inside a
 * transaction that is rolled back afterwards.
 */
abstract class DatabaseTestCase extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    /**
     * An absolute URL on the site, as a browser would request it.
     */
    protected function siteUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }
}
