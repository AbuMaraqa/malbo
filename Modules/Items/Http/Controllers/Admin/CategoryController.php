<?php

namespace Modules\Items\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Http\Requests\Admin\CategoryRequest;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Services\CategoryService;
use Modules\Items\Support\IntegerFilter;
use Modules\Items\Support\RecordInfo;
use Modules\Items\Support\TranslatedSort;
use Spatie\QueryBuilder\AllowedSort;

class CategoryController extends Controller
{
    private const TRANSLATED = ['name', 'description'];

    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
        private readonly CategoryService $categoryService,
    ) {}

    public function index(Request $request): Response
    {
        $query = $this->categories->tableQuery();

        // The default order breaks ties by id; a column the user sorts by replaces it.
        if (! $request->filled('sort')) {
            $query->orderBy('sort_order')->orderBy('id');
        }

        return Inertia::render('Items/Category/Index', [
            'table' => AdminTable::for($query)
                ->search(['slug'])
                ->filters(
                    AdminFilter::translated('name'),
                    IntegerFilter::make('status'),
                    IntegerFilter::make('parent_id'),
                    ...AdminFilter::dateRange('created_at'),
                    ...AdminFilter::numberRange('sort_order'),
                )
                ->sorts(AllowedSort::custom('name', new TranslatedSort), 'slug', 'parent_id', 'sort_order', 'status', 'id')
                ->defaultSort('sort_order')
                ->rows(fn (Category $category) => [
                    'id' => $category->id,
                    'image' => $category->getImageAttribute(),
                    'name' => (string) $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'parent' => $category->parent?->name,
                    'sort_order' => (int) $category->sort_order,
                    'active' => $category->status === CategoryStatus::ACTIVE,
                    'locales' => $category->translations->pluck('locale'),
                ]),
            'parents' => fn () => $this->options($this->categories->options()),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Items/Category/Add', [
            'parents' => $this->categories->parentTree(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->categoryService->create(
            $this->attributes($data),
            Translations::fromForm($data, app()->getLocale(), self::TRANSLATED),
            $request->file('image'),
            $request->user()->id,
        );

        return to_route('categories.index')->with('success', __('Category created successfully'));
    }

    public function edit(int $category): Response
    {
        /** @var Category $model */
        $model = $this->categories->query()->with(['translations', 'media'])->findOrFail($category);
        $media = $model->getFirstMedia('category');

        return Inertia::render('Items/Category/Edit', [
            'category' => [
                'id' => $model->id,
                'name' => $model->translate(app()->getLocale(), false)?->name ?? '',
                'description' => $model->translate(app()->getLocale(), false)?->description ?? '',
                'slug' => $model->slug,
                'parent_id' => $model->parent_id,
                'status' => $model->status === CategoryStatus::ACTIVE,
                'sort_order' => (int) $model->sort_order,
            ],
            'images' => $media ? [['id' => $media->id, 'url' => $media->getUrl()]] : [],
            'parents' => $this->categories->parentTree($model),
        ]);
    }

    public function update(CategoryRequest $request, int $category): RedirectResponse
    {
        /** @var Category $model */
        $model = $this->categories->findOrFail($category);
        $data = $request->validated();

        $this->categoryService->update(
            $model,
            $this->attributes($data),
            Translations::fromForm($data, app()->getLocale(), self::TRANSLATED),
            $request->file('image'),
        );

        return to_route('categories.index')->with('success', __('Category updated successfully'));
    }

    public function status(Request $request, int $category): RedirectResponse
    {
        $active = $request->validate(['active' => ['required', 'boolean']])['active'];

        /** @var Category $model */
        $model = $this->categories->findOrFail($category);

        $this->categoryService->setActive($model, (bool) $active);

        return back()->with('success', __('Category updated successfully'));
    }

    public function destroy(int $category): RedirectResponse
    {
        /** @var Category $model */
        $model = $this->categories->findOrFail($category);

        $this->categoryService->forceDelete($model);

        return back()->with('success', __('Category deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->categoryService->forceDeleteMany($this->categories->query()->whereKey($ids)->get());

        return back()->with('success', __('Categories deleted successfully'));
    }

    public function info(int $category): JsonResponse
    {
        return response()->json(RecordInfo::of($this->categories->findOrFail($category)));
    }

    /**
     * The category columns of the form; the toggle holds true/false, the column a CategoryStatus.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'slug' => $data['slug'],
            'parent_id' => $data['parent_id'] ?? null,
            'status' => ($data['status'] ?? true) ? CategoryStatus::ACTIVE : CategoryStatus::INACTIVE,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /**
     * @param  array<int, string|null>  $names  id => name
     * @return list<array{value: int, label: string}>
     */
    private function options(array $names): array
    {
        return collect($names)->map(fn (?string $name, int $id) => ['value' => $id, 'label' => (string) $name])->values()->all();
    }
}
