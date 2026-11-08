<?php

namespace Modules\Items\Support;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

/**
 * Sorts a table by a translated column in the current locale, through a subquery on the
 * translations table: `AllowedSort::custom('name', new TranslatedSort)`.
 */
class TranslatedSort implements Sort
{
    public function __construct(private readonly string $column = 'name') {}

    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $model = $query->getModel();
        $translationClass = $model->getTranslationModelName();
        $translation = new $translationClass;

        $query->orderBy(
            $translation->newQuery()
                ->select($this->column)
                ->whereColumn($translation->getTable().'.'.$model->getTranslationRelationKey(), $model->getQualifiedKeyName())
                ->where($translation->getTable().'.'.$model->getLocaleKey(), app()->getLocale())
                ->limit(1),
            $descending ? 'desc' : 'asc',
        );
    }
}
