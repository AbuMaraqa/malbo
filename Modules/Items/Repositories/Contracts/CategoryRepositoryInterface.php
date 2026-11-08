<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Items\Entities\Category;

interface CategoryRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin categories table's query with its eager loads (the table orders it).
     */
    public function tableQuery(): Builder;

    /**
     * Every category, id => name in the current locale, by id (product forms and filters).
     *
     * @return array<int, string>
     */
    public function options(): array;

    /**
     * Active categories that have a translation, id => name (parent category choice).
     *
     * @return array<int, string>
     */
    public function activeOptions(): array;

    /**
     * The tree behind the parent category choice: active categories with a translation, as nested
     * `{id, label, children}`. Without $except itself and its children, which cannot be its parent;
     * its current parent stays in the tree even when inactive, so the form still shows it.
     *
     * @return list<array{id: int, label: string, children: array<int, mixed>}>
     */
    public function parentTree(?Category $except = null): array;

    /**
     * The category's id and the ids of all its child categories, at any depth and in any status.
     *
     * @return array<int, int>
     */
    public function selfAndDescendantIds(Category $category): array;

    /**
     * @return array<int, int>
     */
    public function activeIds(): array;

    /**
     * Active root categories with their active children, by sort order then id.
     *
     * @return Collection<int, Category>
     */
    public function tree(): Collection;

    public function findBySlug(string $slug): ?Category;

    /**
     * An active category by slug, for the storefront.
     */
    public function findActiveBySlug(string $slug): ?Category;

    /**
     * Slugs of the active categories, for the sitemap.
     *
     * @return array<int, string>
     */
    public function activeSlugs(): array;

    /**
     * The category's id and the ids of its active child categories, at any depth (an inactive one hides its own).
     *
     * @return array<int, int>
     */
    public function withDescendantIds(Category $category): array;

    /**
     * Categories updated after $since, for the app's sync.
     *
     * @return Collection<int, Category>
     */
    public function changedSince(string $since): Collection;
}
