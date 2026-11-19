<?php

namespace Modules\Store\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Store\Entities\HeroSlide;
use Modules\Store\Repositories\Contracts\HeroSlideRepositoryInterface;

class HeroSlideRepository extends BaseEloquentRepository implements HeroSlideRepositoryInterface
{
    public function __construct(HeroSlide $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['translations', 'media']);
    }

    public function forEdit(int $id): HeroSlide
    {
        /** @var HeroSlide */
        return $this->query()->with(['translations', 'media'])->findOrFail($id);
    }

    public function live(int $limit): Collection
    {
        return $this->whereLive($this->query(), true)
            ->with(['translations', 'media'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function whereLive(Builder $query, bool $live): Builder
    {
        $now = Carbon::now();
        $shown = fn (Builder $query) => $query->where('is_active', true)
            ->where(fn (Builder $start) => $start->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $end) => $end->whereNull('ends_at')->orWhere('ends_at', '>=', $now));

        return $live ? $shown($query) : $query->whereNot(fn (Builder $not) => $shown($not));
    }
}
