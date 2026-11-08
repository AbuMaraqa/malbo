<?php

namespace Modules\Items\Support;

/**
 * Moves the product form's meta inputs (meta_title, meta_description, meta_keywords) in and
 * out of `meta_data`, per language: meta_data.seo.{locale}.{title, description, keywords}.
 * Other keys of meta_data, and the other languages' text, are kept.
 */
class ProductSeo
{
    /** form input => key under seo.{locale} */
    private const FIELDS = [
        'meta_title' => 'title',
        'meta_description' => 'description',
        'meta_keywords' => 'keywords',
    ];

    public static function pack(array $formData, string $locale, ?array $metaData): ?array
    {
        $seo = [];

        foreach (self::FIELDS as $input => $key) {
            $value = $formData[$input] ?? null;
            $seo[$key] = is_string($value) && trim($value) === '' ? null : $value;
        }

        $hasText = array_filter($seo, fn ($value) => $value !== null) !== [];

        // Nothing typed and nothing stored for this language yet: leave meta_data as it was.
        if (! $hasText && ! isset($metaData['seo'][$locale])) {
            return $metaData;
        }

        $metaData ??= [];
        $metaData['seo'][$locale] = $seo;

        return $metaData;
    }

    /**
     * @return array{meta_title: ?string, meta_description: ?string, meta_keywords: ?string}
     */
    public static function unpack(?array $metaData, string $locale): array
    {
        $fields = [];

        foreach (self::FIELDS as $input => $key) {
            $fields[$input] = $metaData['seo'][$locale][$key] ?? null;
        }

        return $fields;
    }
}
