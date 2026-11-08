<?php

namespace Modules\GeoLocation\Exceptions;

use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * A location that other records point to cannot be deleted: the foreign keys cascade, so it would
 * take branches, and through them the staff of those branches, with it.
 */
class LocationInUseException extends RuntimeException
{
    public function render(): RedirectResponse
    {
        return back()->with('error', $this->getMessage());
    }
}
