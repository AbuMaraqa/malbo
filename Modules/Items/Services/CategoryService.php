<?php

namespace Modules\Items\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;

/**
 * Writes categories: attributes, translations (explicit, per locale) and the image, together.
 */
class CategoryService
{
    public function __construct(private CategoryRepositoryInterface $categories) {}

    /**
     * @param  array<string, mixed>  $attributes  slug, parent_id, status, sort_order
     * @param  array<string, array<string, mixed>>  $translations  locale => [name, description]
     * @param  mixed  $image  an uploaded file, or the form's upload state (Category::addImage ignores anything else)
     */
    public function create(array $attributes, array $translations, mixed $image, int $createdBy): Category
    {
        return DB::transaction(function () use ($attributes, $translations, $image, $createdBy) {
            /** @var Category $category */
            $category = $this->categories->create([...$attributes, 'created_by' => $createdBy]);

            Translations::fill($category, $translations);
            $category->save();

            if ($image) {
                $category->addImage($image);
            }

            return $category;
        });
    }

    /**
     * `created_by` is never changed by an update.
     */
    public function update(Category $category, array $attributes, array $translations, mixed $image = null): Category
    {
        unset($attributes['created_by']);

        return DB::transaction(function () use ($category, $attributes, $translations, $image) {
            Translations::fill($category, $translations);
            $this->categories->update($category, $attributes);

            if ($image) {
                $category->addImage($image);
            }

            return $category;
        });
    }

    public function setActive(Category $category, bool $active): Category
    {
        return $this->categories->update($category, ['status' => $active ? CategoryStatus::ACTIVE : CategoryStatus::INACTIVE]);
    }

    public function delete(Category $category): void
    {
        $this->categories->delete($category);
    }

    public function forceDelete(Category $category): void
    {
        $category->forceDelete();
    }

    /**
     * The table's bulk delete, permanent like its single delete: nothing can restore a soft deleted
     * category, and one would keep its slug taken and hide its children from the tree.
     *
     * @param  iterable<int, Category>  $categories
     */
    public function forceDeleteMany(iterable $categories): void
    {
        DB::transaction(function () use ($categories): void {
            foreach ($categories as $category) {
                $category->forceDelete();
            }
        });
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    public function deleteMany(Collection $categories): void
    {
        DB::transaction(fn () => $categories->each(fn (Category $category) => $this->categories->delete($category)));
    }
}
