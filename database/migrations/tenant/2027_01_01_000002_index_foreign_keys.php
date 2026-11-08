<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MySQL indexes foreign keys by itself, PostgreSQL does not: without this, joins and cascading
 * deletes on these columns scan whole tables. A key counts as indexed when an existing index
 * starts with its columns. tests/Feature/Database/ForeignKeysAreIndexedTest keeps the count at zero.
 */
return new class extends Migration
{
    public const UNINDEXED_FOREIGN_KEYS = <<<'SQL'
        SELECT con.conrelid::regclass::text AS table_name,
               string_agg(quote_ident(att.attname), ', ' ORDER BY keys.ord) AS columns
        FROM pg_constraint con
        CROSS JOIN LATERAL unnest(con.conkey) WITH ORDINALITY AS keys(attnum, ord)
        JOIN pg_attribute att ON att.attrelid = con.conrelid AND att.attnum = keys.attnum
        WHERE con.contype = 'f'
          AND con.connamespace = 'public'::regnamespace
          AND NOT EXISTS (
              SELECT 1 FROM pg_index idx
              WHERE idx.indrelid = con.conrelid
                AND (idx.indkey::int2[])[0:cardinality(con.conkey) - 1] @> con.conkey
                AND (idx.indkey::int2[])[0:cardinality(con.conkey) - 1] <@ con.conkey
          )
        GROUP BY con.oid, con.conrelid
        SQL;

    public function up(): void
    {
        foreach (DB::select(self::UNINDEXED_FOREIGN_KEYS) as $key) {
            $name = substr(preg_replace('/\W+/', '_', $key->table_name.'_'.$key->columns), 0, 55).'_fk_idx';

            DB::statement("CREATE INDEX IF NOT EXISTS \"{$name}\" ON {$key->table_name} ({$key->columns})");
        }
    }

    public function down(): void
    {
        //
    }
};
