<?php

namespace Modules\Store\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Store\Entities\HeroSlide;

interface HeroSlideRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table's query: slides with their texts and pictures.
     */
    public function tableQuery(): Builder;

    /**
     * A slide with its texts and pictures, for its form.
     */
    public function forEdit(int $id): HeroSlide;

    /**
     * The slides the storefront shows now (active, inside their dates), in their order, at most $limit.
     *
     * @return Collection<int, HeroSlide>
     */
    public function live(int $limit): Collection;

    /**
     * Narrows a query to the slides shown now ($live true) or to the others.
     */
    public function whereLive(Builder $query, bool $live): Builder;
}
