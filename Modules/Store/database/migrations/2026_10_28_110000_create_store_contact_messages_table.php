<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages buyers send from the storefront's contact page, read by the staff in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('subject', 160)->nullable();
            $table->text('message');
            $table->string('locale', 5)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_contact_messages');
    }
};
