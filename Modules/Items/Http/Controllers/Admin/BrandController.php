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
use Modules\Items\Entities\Brand;
use Modules\Items\Http\Requests\Admin\BrandRequest;
use Modules\Items\Repositories\Contracts\BrandRepositoryInterface;
use Modules\Items\Services\BrandService;
use Modules\Items\Support\RecordInfo;
use Modules\Items\Support\TranslatedSort;
use Spatie\QueryBuilder\AllowedSort;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandRepositoryInterface $brands,
        private readonly BrandService $brandService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Items/Brand/Index', [
            'table' => AdminTable::for($this->brands->tableQuery())
                ->search(['slug'])
                ->filters(AdminFilter::translated('name'), ...AdminFilter::dateRange('created_at'))
                ->sorts(AllowedSort::custom('name', new TranslatedSort), 'slug', 'id')
                ->rows(function (Brand $brand) {
                    $media = $brand->getFirstMedia('brand');

                    return [
                        'id' => $brand->id,
                        'image' => $media?->getUrl(),
                        'image_id' => $media?->id,
                        'name' => (string) $brand->name,
                        'slug' => $brand->slug,
                        'description' => $brand->description,
                        'locales' => $brand->translations->pluck('locale'),
                        // The modal edits the current locale only, without the fallback the table shows.
                        'form' => [
                            'name' => $brand->translate(app()->getLocale(), false)?->name ?? '',
                            'description' => $brand->translate(app()->getLocale(), false)?->description ?? '',
                        ],
                    ];
                }),
        ]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        $this->brandService->create($request->validated(), $request->file('image'), app()->getLocale(), $request->user()->id);

        return back()->with('success', __('Brand created successfully'));
    }

    public function update(BrandRequest $request, int $brand): RedirectResponse
    {
        /** @var Brand $model */
        $model = $this->brands->query()->with('media')->findOrFail($brand);
        $data = $request->validated();

        $this->brandService->update($model, $data, $request->file('image'), $data['removed_image'] ?? [], app()->getLocale());

        return back()->with('success', __('Brand updated successfully'));
    }

    public function destroy(int $brand): RedirectResponse
    {
        /** @var Brand $model */
        $model = $this->brands->findOrFail($brand);

        $this->brandService->delete($model);

        return back()->with('success', __('Brand deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->brandService->deleteMany($ids);

        return back()->with('success', __('Brands deleted successfully'));
    }

    public function info(int $brand): JsonResponse
    {
        return response()->json(RecordInfo::of($this->brands->findOrFail($brand)));
    }
}
