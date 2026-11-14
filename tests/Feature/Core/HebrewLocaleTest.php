<?php

namespace Tests\Feature\Core;

use App\Support\Locales;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\DatabaseTestCase;

class HebrewLocaleTest extends DatabaseTestCase
{
    public function test_hebrew_is_a_right_to_left_locale(): void
    {
        $this->assertSame(['ar', 'en', 'he'], Locales::keys());
        $this->assertTrue(Locales::isRtl('he'));
    }

    public function test_the_storefront_answers_in_hebrew(): void
    {
        $this->get($this->siteUrl('/he'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'he')->where('dir', 'rtl'));
    }

    public function test_a_missing_hebrew_text_falls_back_to_english(): void
    {
        app()->setLocale('he');

        $this->assertSame('Dashboard', __('Dashboard'));
    }
}
