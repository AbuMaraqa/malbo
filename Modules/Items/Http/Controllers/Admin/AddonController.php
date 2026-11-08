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
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Enums\AddonType;
use Modules\Items\Http\Requests\Admin\AddonRequest;
use Modules\Items\Repositories\Contracts\AddonRepositoryInterface;
use Modules\Items\Services\AddonService;
use Modules\Items\Support\IntegerFilter;
use Modules\Items\Support\RecordInfo;
use Modules\Items\Support\TranslatedSort;
use Spatie\QueryBuilder\AllowedSort;

class AddonController extends Controller
{
    public function __construct(
        private readonly AddonRepositoryInterface $addons,
        private readonly AddonService $addonService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Items/Addon/Index', [
            'table' => AdminTable::for($this->addons->tableQuery())
                ->search([])
                ->filters(AdminFilter::translated('name'), IntegerFilter::make('type'), ...AdminFilter::dateRange('created_at'))
                ->sorts(AllowedSort::custom('name', new TranslatedSort), 'type', 'id')
                ->rows(fn (Addon $addon) => [
                    'id' => $addon->id,
                    'image' => $addon->getImageAttribute(),
                    'name' => (string) $addon->name,
                    'description' => $addon->description,
                    'type' => $addon->type?->getLabel(),
                    'locales' => $addon->translations->pluck('locale'),
                ]),
            'types' => $this->types(),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Items/Addon/Add', ['types' => $this->types()]);
    }

    public function store(AddonRequest $request): RedirectResponse
    {
        $this->addonService->create($request->validated(), app()->getLocale(), $request->user()->id);

        return to_route('addons.index')->with('success', __('Addon created successfully'));
    }

    public function edit(int $addon): Response
    {
        $model = $this->addons->findForForm($addon);
        $locale = app()->getLocale();

        return Inertia::render('Items/Addon/Edit', [
            'addon' => [
                'id' => $model->id,
                'name' => $model->translate($locale, false)?->name ?? '',
                'type' => $model->type?->value,
                'description' => $model->translate($locale, false)?->description ?? '',
                'options' => $model->addonOptions->map(function (AddonOption $option) use ($locale) {
                    // An option not translated yet opens with its first translation, to edit from.
                    $translation = $option->translate($locale, false) ?? $option->translations->first();
                    $media = $option->getFirstMedia('addon_option');

                    return [
                        'id' => $option->id,
                        'name' => $translation?->name ?? '',
                        'description' => $translation?->description ?? '',
                        'price' => $option->price,
                        'is_default' => (bool) $option->is_default,
                        'is_quantifiable' => (bool) $option->is_quantifiable,
                        'images' => $media ? [['id' => $media->id, 'url' => $media->getUrl()]] : [],
                    ];
                })->values(),
            ],
            'types' => $this->types(),
        ]);
    }

    public function update(AddonRequest $request, int $addon): RedirectResponse
    {
        /** @var Addon $model */
        $model = $this->addons->findOrFail($addon);

        $this->addonService->update($model, $request->validated(), app()->getLocale());

        return to_route('addons.index')->with('success', __('Addon updated successfully'));
    }

    public function destroy(int $addon): RedirectResponse
    {
        /** @var Addon $model */
        $model = $this->addons->findOrFail($addon);

        $this->addonService->delete($model);

        return back()->with('success', __('Addon deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->addonService->deleteMany($ids);

        return back()->with('success', __('Addons deleted successfully'));
    }

    public function info(int $addon): JsonResponse
    {
        return response()->json(RecordInfo::of($this->addons->findOrFail($addon)));
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function types(): array
    {
        return array_map(
            fn (AddonType $type) => ['value' => $type->value, 'label' => $type->getLabel()],
            AddonType::cases(),
        );
    }
}
