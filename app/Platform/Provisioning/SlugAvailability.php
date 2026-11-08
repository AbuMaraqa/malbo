<?php

declare(strict_types=1);

namespace App\Platform\Provisioning;

use App\Models\Tenant;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Whether a slug can become a new store id (and its subdomain).
 */
class SlugAvailability
{
    public const FORMAT = '/^[a-z0-9](-?[a-z0-9]){2,29}$/';

    public static function normalize(string $slug): string
    {
        return Str::lower(trim($slug));
    }

    /**
     * @return 'format'|'reserved'|'taken'|null null when the slug is available
     */
    public static function check(string $slug): ?string
    {
        if (preg_match(self::FORMAT, $slug) !== 1) {
            return 'format';
        }

        if (Str::is(config('store.reserved_slugs', []), $slug)) {
            return 'reserved';
        }

        $domain = $slug.'.'.config('tenancy.base_domain');

        if (Tenant::whereKey($slug)->exists() || Domain::where('domain', $domain)->exists()) {
            return 'taken';
        }

        return null;
    }

    public static function message(string $reason): string
    {
        return match ($reason) {
            'format' => __('The store address must be 3 to 30 lowercase letters or digits, with single hyphens between them.'),
            'reserved' => __('This store address is reserved.'),
            'taken' => __('This store address is already taken.'),
        };
    }
}
