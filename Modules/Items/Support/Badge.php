<?php

namespace Modules\Items\Support;

/**
 * The badge markup shared by tags and labels. Every value is escaped, colors included.
 */
class Badge
{
    public static function html(?string $text, ?string $color, ?string $background): string
    {
        return "<span class='px-2 py-1 rounded text-sm font-medium' style='color: ".e((string) $color)
            .'; background-color: '.e((string) $background).";'>".e((string) $text).'</span>';
    }
}
