<?php

namespace Modules\Items\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Items\Entities\Tag;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'name' => 'وسم '.Str::random(5),
            'slug' => 'tag-'.Str::lower(Str::random(8)),
            'text_color' => '#FFFFFF',
            'background_color' => '#1F6F50',
        ];
    }
}
