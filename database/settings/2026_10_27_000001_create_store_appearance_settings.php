<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $empty = ['ar' => '', 'en' => ''];

        $this->migrator->add('store_appearance.primary_color', '#1F6F50');
        $this->migrator->add('store_appearance.font', 'tajawal');
        $this->migrator->add('store_appearance.logo_path', null);
        $this->migrator->add('store_appearance.favicon_path', null);
        $this->migrator->add('store_appearance.announcement', $empty);
        $this->migrator->add('store_appearance.hero_eyebrow', $empty);
        $this->migrator->add('store_appearance.hero_title', $empty);
        $this->migrator->add('store_appearance.hero_subtitle', $empty);
        $this->migrator->add('store_appearance.hero_image_path', null);
        $this->migrator->add('store_appearance.story_title', $empty);
        $this->migrator->add('store_appearance.story_text', $empty);
        $this->migrator->add('store_appearance.story_image_path', null);
        $this->migrator->add('store_appearance.values', []);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('store_appearance.primary_color');
        $this->migrator->deleteIfExists('store_appearance.font');
        $this->migrator->deleteIfExists('store_appearance.logo_path');
        $this->migrator->deleteIfExists('store_appearance.favicon_path');
        $this->migrator->deleteIfExists('store_appearance.announcement');
        $this->migrator->deleteIfExists('store_appearance.hero_eyebrow');
        $this->migrator->deleteIfExists('store_appearance.hero_title');
        $this->migrator->deleteIfExists('store_appearance.hero_subtitle');
        $this->migrator->deleteIfExists('store_appearance.hero_image_path');
        $this->migrator->deleteIfExists('store_appearance.story_title');
        $this->migrator->deleteIfExists('store_appearance.story_text');
        $this->migrator->deleteIfExists('store_appearance.story_image_path');
        $this->migrator->deleteIfExists('store_appearance.values');
    }
};
