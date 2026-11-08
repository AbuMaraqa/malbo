<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Case-insensitive `ILIKE '%term%'` searches use GIN trigram indexes instead of a table scan.
 */
return new class extends Migration
{
    private const TRANSLATED_NAMES = [
        'items_product_translations',
        'items_category_translations',
    ];

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        foreach (self::TRANSLATED_NAMES as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::statement("CREATE INDEX IF NOT EXISTS {$table}_name_trgm ON {$table} USING gin (name gin_trgm_ops)");
            }
        }
    }

    public function down(): void
    {
        foreach (self::TRANSLATED_NAMES as $table) {
            DB::statement("DROP INDEX IF EXISTS {$table}_name_trgm");
        }
    }
};
