<?php

namespace Modules\Items\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Entities\Category;
use Modules\Items\Http\Requests\Admin\Concerns\NormalizesSlug;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;

class CategoryRequest extends FormRequest
{
    use NormalizesSlug;

    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $table = (new Category)->getTable();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique($table, 'slug')->ignore($this->route('category'))],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists($table, 'id')->whereNull('deleted_at'),
                $this->notInsideItself(...),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'image' => [$this->imageIsRequired() ? 'required' : 'nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
            'removed_image' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'slug' => __('Slug'),
            'parent_id' => __('Parent Category'),
            'description' => __('Description'),
            'sort_order' => __('Sort Order'),
            'image' => __('Image'),
        ];
    }

    /**
     * A category needs an image: a new one always, a saved one when it has none or its image was removed.
     */
    private function imageIsRequired(): bool
    {
        $id = $this->route('category');

        if ($id === null || ! empty($this->input('removed_image'))) {
            return true;
        }

        return ! app(CategoryRepositoryInterface::class)->find($id)?->hasMedia('category');
    }

    /**
     * A category under itself or under one of its children would leave the tree.
     */
    private function notInsideItself(string $attribute, mixed $value, Closure $fail): void
    {
        $id = $this->route('category');

        if ($id === null) {
            return;
        }

        $categories = app(CategoryRepositoryInterface::class);
        $category = $categories->find($id);

        if ($category && in_array((int) $value, $categories->selfAndDescendantIds($category), true)) {
            $fail(__('A category cannot be placed under itself or one of its children.'));
        }
    }
}
