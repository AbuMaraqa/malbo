<?php

namespace Modules\Items\Support;

use Modules\Items\Entities\Tag;

/**
 * A tag as a colored badge (HTML) for tables.
 */
class TagBadge
{
    public static function render(Tag $tag): string
    {
        return Badge::html($tag->name, $tag->text_color, $tag->background_color);
    }
}
