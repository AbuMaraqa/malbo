<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

class ForeignKeysAreIndexedTest extends TenantTestCase
{
    public function test_every_foreign_key_has_an_index(): void
    {
        $migration = require database_path('migrations/tenant/2027_01_01_000002_index_foreign_keys.php');

        $this->assertSame([], DB::select($migration::UNINDEXED_FOREIGN_KEYS));
    }

    public function test_translated_names_have_a_trigram_index(): void
    {
        $this->assertSame(2, DB::table('pg_indexes')->whereLike('indexname', '%_name_trgm')->count());
    }
}
