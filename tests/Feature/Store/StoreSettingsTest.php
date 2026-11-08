<?php

namespace Tests\Feature\Store;

use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Settings\StoreContactSettings;
use Tests\DatabaseTestCase;

class StoreSettingsTest extends DatabaseTestCase
{
    public function test_a_store_has_the_appearance_defaults(): void
    {
        $appearance = app(StoreAppearanceSettings::class);

        $this->assertSame('#1F6F50', $appearance->primary_color);
        $this->assertSame('tajawal', $appearance->font);
        $this->assertNull($appearance->logo_path);
        $this->assertSame(['ar' => '', 'en' => ''], $appearance->hero_title);
        $this->assertSame([], $appearance->values);
    }

    public function test_a_store_has_empty_contact_details(): void
    {
        $contact = app(StoreContactSettings::class);

        $this->assertNull($contact->whatsapp);
        $this->assertNull($contact->phone);
        $this->assertSame(['ar' => '', 'en' => ''], $contact->address);
    }
}
