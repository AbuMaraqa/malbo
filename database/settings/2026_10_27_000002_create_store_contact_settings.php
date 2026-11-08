<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('store_contact.phone', null);
        $this->migrator->add('store_contact.whatsapp', null);
        $this->migrator->add('store_contact.email', null);
        $this->migrator->add('store_contact.address', ['ar' => '', 'en' => '']);
        $this->migrator->add('store_contact.facebook_url', null);
        $this->migrator->add('store_contact.instagram_url', null);
        $this->migrator->add('store_contact.tiktok_url', null);
        $this->migrator->add('store_contact.x_url', null);
    }

    public function down(): void
    {
        foreach (['phone', 'whatsapp', 'email', 'address', 'facebook_url', 'instagram_url', 'tiktok_url', 'x_url'] as $name) {
            $this->migrator->deleteIfExists("store_contact.{$name}");
        }
    }
};
