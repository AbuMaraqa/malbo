<?php

namespace Modules\Items\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Items\Entities\Label;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    protected $model = Label::class;

    public function definition(): array
    {
        return [
            'name' => 'ملصق '.Str::random(5),
            'slug' => 'label-'.Str::lower(Str::random(8)),
            'text_color' => '#FFFFFF',
            'background_color' => '#B42335',
            'icon' => null,
        ];
    }
}
