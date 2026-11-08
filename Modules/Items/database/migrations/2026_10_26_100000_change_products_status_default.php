<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Items\Enums\ProductStatus;

return new class extends Migration
{
    /**
     * New products are Published unless someone chooses Draft (status had no form field, so the
     * Draft default made every product invisible to a storefront).
     */
    public function up(): void
    {
        Schema::table(config('items.table_prefix').'products', function (Blueprint $table) {
            $table->integer('status')->default(ProductStatus::PUBLISHED->value)->change();
        });
    }

    public function down(): void
    {
        Schema::table(config('items.table_prefix').'products', function (Blueprint $table) {
            $table->integer('status')->default(ProductStatus::DRAFT->value)->change();
        });
    }
};
