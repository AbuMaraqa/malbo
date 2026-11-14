<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Arabic spelling varies in what buyers type: `items_search_norm(text)` puts both sides in one form
 * before the shop's search compares them (ProductRepository::search):
 * - lower case;
 * - أ إ آ ٱ → ا, ة → ه, ى → ي;
 * - tashkeel (U+064B–U+0652) and tatweel (U+0640) removed.
 * Immutable, so names are indexed through it: trigram indexes for `LIKE '%…%'` on the normalised
 * names of products, categories and tags. The plain `name` trigram indexes stay: the admin's
 * searches still use ILIKE on the names.
 */
return new class extends Migration
{
    private const TABLES = ['product_translations', 'category_translations', 'tag_translations'];

    public function up(): void
    {
        $from = 'أإآٱةى'.mb_chr(0x0640);
        foreach (range(0x064B, 0x0652) as $mark) {
            $from .= mb_chr($mark);
        }
        $to = 'ااااهي'; // characters of $from beyond these are removed

        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION items_search_norm(value text) RETURNS text
            LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE
            AS \$\$ SELECT translate(lower(value), '{$from}', '{$to}') \$\$
            SQL);

        $prefix = config('items.table_prefix');

        foreach (self::TABLES as $table) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$prefix}{$table}_search_norm_trgm ON {$prefix}{$table} USING gin (items_search_norm(name) gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        $prefix = config('items.table_prefix');

        foreach (self::TABLES as $table) {
            DB::statement("DROP INDEX IF EXISTS {$prefix}{$table}_search_norm_trgm");
        }

        DB::statement('DROP FUNCTION IF EXISTS items_search_norm(text)');
    }
};
