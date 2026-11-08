<?php

namespace Modules\Items\Http\Requests\Admin\Concerns;

/**
 * A color kept in the database and written into a style attribute: hex only.
 */
final class ColorRule
{
    public const HEX = 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/';
}
