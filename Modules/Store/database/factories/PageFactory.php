<?php

namespace Modules\Store\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Store\Entities\Page;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'slug' => 'page-'.Str::lower(Str::random(8)),
            'title' => 'صفحة '.Str::random(5),
            'body' => '<p>نص الصفحة</p>',
            'is_published' => true,
            'show_in_footer' => true,
            'sort_order' => 100,
        ];
    }
}
