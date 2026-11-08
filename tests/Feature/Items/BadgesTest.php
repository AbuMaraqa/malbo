<?php

namespace Tests\Feature\Items;

use Modules\Items\Entities\Label;
use Modules\Items\Entities\Tag;
use Modules\Items\Support\LabelBadge;
use Modules\Items\Support\TagBadge;
use Tests\DatabaseTestCase;

class BadgesTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
    }

    public function test_tag_badge_shows_escaped_name_in_its_colors(): void
    {
        $html = TagBadge::render(Tag::factory()->create(['name' => '<b>جديد</b>', 'text_color' => '#FFFFFF', 'background_color' => '#1F6F50']));

        $this->assertStringContainsString('color: #FFFFFF', $html);
        $this->assertStringContainsString('background-color: #1F6F50', $html);
        $this->assertStringContainsString('&lt;b&gt;جديد&lt;/b&gt;', $html);
    }

    public function test_label_badge_shows_escaped_name_in_its_colors(): void
    {
        $html = LabelBadge::render(Label::factory()->create(['name' => 'عرض', 'text_color' => '#111827', 'background_color' => '#F5C518']));

        $this->assertStringContainsString('color: #111827', $html);
        $this->assertStringContainsString('background-color: #F5C518', $html);
        $this->assertStringContainsString('عرض', $html);
    }

    public function test_colors_cannot_break_out_of_the_style_attribute(): void
    {
        $html = TagBadge::render(Tag::factory()->create(['text_color' => "red' onmouseover='x"]));

        $this->assertStringNotContainsString("' onmouseover='", $html);
    }
}
