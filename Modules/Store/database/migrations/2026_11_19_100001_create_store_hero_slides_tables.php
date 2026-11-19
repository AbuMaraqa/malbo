<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The home page slider's slides, entered in the admin: a picture for wide screens (and another for
 * phones when staff give one), a link, an order, a switch and optional dates; their texts per language.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('store.table_prefix');

        Schema::create($prefix.'hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('link', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create($prefix.'hero_slide_translations', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('hero_slide_id')->constrained($prefix.'hero_slides')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('eyebrow', 120)->nullable();
            $table->string('title', 160)->nullable();
            $table->string('subtitle', 300)->nullable();
            $table->string('button_label', 60)->nullable();
            $table->unique(['hero_slide_id', 'locale']);
        });
    }

    public function down(): void
    {
        $prefix = config('store.table_prefix');

        Schema::dropIfExists($prefix.'hero_slide_translations');
        Schema::dropIfExists($prefix.'hero_slides');
    }
};
