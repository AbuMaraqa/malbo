<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Items\Enums\ProductStatus;

return new class extends Migration
{
    /**
     * Status had no form field, so no store ever chose "draft": every draft product (variations
     * and trashed rows included) is published. The query builder leaves updated_at alone, so sync
     * clients do not download every product again.
     */
    public function up(): void
    {
        DB::table(config('items.table_prefix').'products')
            ->where('status', ProductStatus::DRAFT->value)
            ->update(['status' => ProductStatus::PUBLISHED->value]);
    }

    /**
     * Which products were drafts before is not recorded, so there is nothing to undo.
     */
    public function down(): void
    {
        //
    }
};
