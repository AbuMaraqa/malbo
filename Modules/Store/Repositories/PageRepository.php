<?php

namespace Modules\Store\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Store\Entities\Page;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;

class PageRepository extends BaseEloquentRepository implements PageRepositoryInterface
{
    public function __construct(Page $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with('translations');
    }

    public function findByKey(string $key): ?Page
    {
        return $this->query()->with('translations')->where('key', $key)->first();
    }

    public function findPublishedBySlug(string $slug): ?Page
    {
        return $this->query()->with('translations')->where('slug', $slug)->where('is_published', true)->first();
    }

    public function footerPages(): Collection
    {
        return $this->publishedQuery()->where('show_in_footer', true)->get();
    }

    public function published(): Collection
    {
        return $this->publishedQuery()->get();
    }

    private function publishedQuery(): Builder
    {
        return $this->query()->with('translations')->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }

    public function publishedKeys(): array
    {
        return $this->query()->whereNotNull('key')->where('is_published', true)->pluck('slug', 'key')->all();
    }

    public function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug) ?: 'page';
        $candidate = $base;

        for ($counter = 1; $this->slugTaken($candidate, $ignoreId); $counter++) {
            $candidate = $base.'-'.$counter;
        }

        return $candidate;
    }

    private function slugTaken(string $slug, ?int $ignoreId): bool
    {
        // A deleted page keeps its slug (the column is unique), so it still counts as taken.
        return $this->query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
