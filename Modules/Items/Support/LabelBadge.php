<?php

namespace Modules\Items\Support;

use Modules\Items\Entities\Label;

/**
 * A label as a colored badge (HTML) for tables.
 */
class LabelBadge
{
    public static function render(Label $label): string
    {
        return Badge::html($label->name, $label->text_color, $label->background_color);
    }
}
