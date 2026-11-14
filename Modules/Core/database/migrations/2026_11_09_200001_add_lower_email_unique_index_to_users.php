<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One account per email whatever its letter case: Buyer@ and buyer@ are the same person. Emails that
 * already differ only by case stop the migration with a list, so someone decides which account
 * stays; nothing is merged or deleted here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('users')
            ->selectRaw('lower(email) as email, count(*) as accounts, string_agg(id::text, \', \' order by id) as ids')
            ->groupByRaw('lower(email)')
            ->havingRaw('count(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException("Users whose emails differ only by letter case must be resolved first:\n".$duplicates
                ->map(fn (object $row) => "  {$row->email}: {$row->accounts} accounts (ids {$row->ids})")
                ->implode("\n"));
        }

        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
