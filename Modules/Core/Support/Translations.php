<?php

namespace Modules\Core\Support;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes translations explicitly. Models never read them from the current request, so a
 * console import or a queued job stores exactly what it is given.
 */
class Translations
{
    /**
     * @param  array<string, array<string, mixed>>  $translations  locale => [field => value]; empty locales are skipped
     */
    public static function fill(Model&TranslatableContract $model, array $translations): void
    {
        foreach ($translations as $locale => $fields) {
            $fields = array_intersect_key($fields, array_flip($model->translatedAttributes));

            if ($fields === []) {
                continue;
            }

            $model->translateOrNew($locale)->fill($fields);
        }
    }

    /**
     * The translated fields of a form that edits one locale.
     *
     * @param  array<int, string>  $fields
     * @return array<string, array<string, mixed>>
     */
    public static function fromForm(array $data, string $locale, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = $data[$field] ?? null;
        }

        return [$locale => $values];
    }
}
