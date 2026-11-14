<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a fashion product page tells: fabric, composition, fit and care (translated), the model in
 * the photos (height in cm, weight in kg, size worn), the new and best seller flags, and a video.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('items.table_prefix').'products', function (Blueprint $table) {
            $table->boolean('is_new')->default(false);
            $table->boolean('is_best_seller')->default(false);
            $table->smallInteger('model_height')->nullable();
            $table->smallInteger('model_weight')->nullable();
            $table->string('model_size', 32)->nullable();
            $table->string('video_url', 500)->nullable();
        });

        Schema::table(config('items.table_prefix').'product_translations', function (Blueprint $table) {
            $table->text('fabric')->nullable();
            $table->text('composition')->nullable();
            $table->text('fit')->nullable();
            $table->text('care')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(config('items.table_prefix').'product_translations', function (Blueprint $table) {
            $table->dropColumn(['fabric', 'composition', 'fit', 'care']);
        });

        Schema::table(config('items.table_prefix').'products', function (Blueprint $table) {
            $table->dropColumn(['is_new', 'is_best_seller', 'model_height', 'model_weight', 'model_size', 'video_url']);
        });
    }
};
