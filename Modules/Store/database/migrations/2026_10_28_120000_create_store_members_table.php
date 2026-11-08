<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitors who joined the store's members list from the home page. One row per phone number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_members', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 40)->unique(); // digits only, so "0599 123 456" and "0599123456" are one member
            $table->string('email', 160)->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_members');
    }
};
