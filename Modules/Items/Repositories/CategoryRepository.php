<?php

namespace Modules\Items\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;

class CategoryRepository extends BaseEloquentRepository implements CategoryRepositoryInterface
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        // No ordering here: the table's defaultSort orders it, and a column the user sorts by must win.
        return $this->query()->with(['createdBy', 'media', 'translations', 'parent.translations']);
    }

    public function options(): array
    {
        return $this->query()
            ->with('translations')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Category $category) => [$category->id => $category->name])
            ->all();
    }

    public function activeOptions(): array
    {
        return $this->query()
            ->with('translations')
            ->translated()
            ->where('status', CategoryStatus::ACTIVE)
            ->get()
            ->mapWithKeys(fn (Category $category) => [$category->id => $category->name])
            ->all();
    }

    public function parentTree(?Category $except = null): array
    {
        $excluded = $except ? $this->selfAndDescendantIds($except) : [];

        $rows = $this->query()
            ->with('translations')
            ->translated()
            ->where(fn ($query) => $query
                ->where('status', CategoryStatus::ACTIVE)
                ->when($except?->parent_id, fn ($query, $parentId) => $query->orWhereKey($parentId)))
            ->when($excluded !== [], fn ($query) => $query->whereKeyNot($excluded))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // A category whose parent is not in the list (inactive) moves up to the root.
        $ids = $rows->pluck('id')->flip();
        $byParent = $rows->groupBy(fn (Category $category) => $ids->has($category->parent_id) ? $category->parent_id : 0);

        $nodes = function (int $parent) use (&$nodes, $byParent): array {
            return $byParent->get($parent, collect())
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'label' => (string) $category->name,
                    'children' => $nodes($category->id),
                ])
                ->values()
                ->all();
        };

        return $nodes(0);
    }

    public function selfAndDescendantIds(Category $category): array
    {
        $ids = [$category->getKey()];
        $level = $ids;

        // One query per level; skipping seen ids stops a parent_id loop.
        while ($level !== []) {
            $level = $this->query()->whereIn('parent_id', $level)->whereKeyNot($ids)->pluck('id')->all();
            $ids = [...$ids, ...$level];
        }

        return $ids;
    }

    public function activeIds(): array
    {
        return $this->query()->where('status', CategoryStatus::ACTIVE)->pluck('id')->all();
    }

    public function tree(): Collection
    {
        $active = fn ($query) => $query->where('status', CategoryStatus::ACTIVE)->orderBy('sort_order')->orderBy('id');

        return $this->query()
            ->whereNull('parent_id')
            ->where('status', CategoryStatus::ACTIVE)
            ->with(['translations', 'children' => $active, 'children.translations'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->query()->where('slug', $slug)->first();
    }

    public function findActiveBySlug(string $slug): ?Category
    {
        return $this->query()->with('translations')->where('slug', $slug)->where('status', CategoryStatus::ACTIVE)->first();
    }

    public function activeSlugs(): array
    {
        return $this->query()->where('status', CategoryStatus::ACTIVE)->orderBy('sort_order')->orderBy('id')->pluck('slug')->all();
    }

    public function withDescendantIds(Category $category): array
    {
        $ids = [$category->getKey()];
        $level = $ids;

        // One query per level; skipping seen ids stops a parent_id loop.
        while ($level !== []) {
            $level = $this->query()->whereIn('parent_id', $level)->where('status', CategoryStatus::ACTIVE)->whereKeyNot($ids)->pluck('id')->all();
            $ids = [...$ids, ...$level];
        }

        return $ids;
    }

    public function changedSince(string $since): Collection
    {
        return $this->query()
            ->with(['media', 'translations'])
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();
    }
}
