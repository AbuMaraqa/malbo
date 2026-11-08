<?php

namespace Modules\Store\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use App\Support\Locales;

/**
 * Small helpers for storefront views: a store text in the current locale, a stored image's URL.
 */
final class Storefront
{
    /**
     * The current locale's text, else the other locale's, else null.
     *
     * @param  array<string, string|null>|null  $value  {ar, en}
     */
    public static function t(?array $value): ?string
    {
        if (! $value) {
            return null;
        }

        $locale = app()->getLocale();
        $text = trim((string) ($value[$locale] ?? ''));

        if ($text === '') {
            $other = collect($value)->except($locale)->map(fn ($v) => trim((string) $v))->first(fn ($v) => $v !== '');
            $text = (string) $other;
        }

        return $text === '' ? null : $text;
    }

    /**
     * The store's values in the current locale, without the ones that have no title.
     *
     * @param  list<array{title?: array, text?: array}>|null  $values
     * @return Collection<int, array{title: string, text: ?string}>
     */
    public static function values(?array $values): Collection
    {
        return collect($values ?? [])
            ->map(fn (array $value) => ['title' => self::t($value['title'] ?? null), 'text' => self::t($value['text'] ?? null)])
            ->filter(fn (array $value) => $value['title'] !== null)
            ->values();
    }

    /**
     * URL of an image saved by StoreAppearanceService (the store's public disk).
     */
    public static function image(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * This page in another language, on the store's own host (the localization package builds
     * it from APP_URL, which is the platform host).
     */
    public static function localeUrl(string $locale, bool $withQuery = false): string
    {
        $segments = request()->segments();

        if ($segments !== [] && in_array($segments[0], \App\Support\Locales::keys(), true)) {
            $segments[0] = $locale;
        } else {
            array_unshift($segments, $locale);
        }

        $url = url(implode('/', $segments));
        $query = request()->getQueryString();

        return $withQuery && $query ? $url.'?'.$query : $url;
    }

    public static function rtl(): bool
    {
        return app()->getLocale() === 'ar';
    }
}
