<?php

namespace Modules\Items\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Http\Requests\Admin\AttributeRequest;
use Modules\Items\Repositories\Contracts\AttributeRepositoryInterface;
use Modules\Items\Services\AttributeService;
use Modules\Items\Support\IntegerFilter;
use Modules\Items\Support\RecordInfo;
use Modules\Items\Support\TranslatedSort;
use Spatie\QueryBuilder\AllowedSort;

class AttributeController extends Controller
{
    public function __construct(
        private readonly AttributeRepositoryInterface $attributes,
        private readonly AttributeService $attributeService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Items/Attribute/Index', [
            'table' => AdminTable::for($this->attributes->tableQuery())
                ->search(['slug'])
                ->filters(AdminFilter::translated('name'), IntegerFilter::make('type'), ...AdminFilter::dateRange('created_at'))
                ->sorts(AllowedSort::custom('name', new TranslatedSort), 'slug', 'type', 'id')
                ->rows(fn (Attribute $attribute) => [
                    'id' => $attribute->id,
                    'name' => (string) $attribute->name,
                    'slug' => $attribute->slug,
                    'description' => $attribute->description,
                    'type' => AttributeType::tryFrom((int) $attribute->type)?->label(),
                    'locales' => $attribute->translations->pluck('locale'),
                ]),
            'types' => $this->types(),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Items/Attribute/Add', [
            'types' => $this->types(),
            'colorType' => AttributeType::COLOR->value,
        ]);
    }

    public function store(AttributeRequest $request): RedirectResponse
    {
        $this->attributeService->create($request->validated(), app()->getLocale(), $request->user()->id);

        return to_route('attributes.index')->with('success', __('Attribute created successfully'));
    }

    public function edit(int $attribute): Response
    {
        $model = $this->attributes->findForForm($attribute);
        $locale = app()->getLocale();

        return Inertia::render('Items/Attribute/Edit', [
            'attribute' => [
                'id' => $model->id,
                'name' => $model->translate($locale, false)?->name ?? '',
                'slug' => $model->slug,
                'type' => (int) $model->type,
                'description' => $model->translate($locale, false)?->description ?? '',
                'values' => $model->attributeValues->map(function (AttributeValue $value) use ($locale) {
                    // A value not translated yet opens with its first translation, to edit from.
                    $translation = $value->translate($locale, false) ?? $value->translations->first();

                    return [
                        'id' => $value->id,
                        'name' => $translation?->name ?? '',
                        'description' => $translation?->description ?? '',
                        'color_code' => $value->color_code ?? '#000000',
                    ];
                })->values(),
            ],
            'types' => $this->types(),
            'colorType' => AttributeType::COLOR->value,
        ]);
    }

    public function update(AttributeRequest $request, int $attribute): RedirectResponse
    {
        /** @var Attribute $model */
        $model = $this->attributes->findOrFail($attribute);

        $this->attributeService->update($model, $request->validated(), app()->getLocale(), $request->user()->id);

        return to_route('attributes.index')->with('success', __('Attribute updated successfully'));
    }

    public function reorderValues(Request $request, int $attribute): RedirectResponse
    {
        /** @var Attribute $model */
        $model = $this->attributes->findOrFail($attribute);

        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', Rule::exists((new AttributeValue)->getTable(), 'id')->where('attribute_id', $model->id)],
        ])['ids'];

        $this->attributeService->reorderValues($model, $ids);

        return back()->with('success', __('Order saved'));
    }

    public function destroy(int $attribute): RedirectResponse
    {
        /** @var Attribute $model */
        $model = $this->attributes->findOrFail($attribute);

        $this->attributeService->delete($model);

        return back()->with('success', __('Attribute deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->attributeService->deleteMany($ids);

        return back()->with('success', __('Attributes deleted successfully'));
    }

    public function info(int $attribute): JsonResponse
    {
        return response()->json(RecordInfo::of($this->attributes->findOrFail($attribute)));
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function types(): array
    {
        return array_map(
            fn (AttributeType $type) => ['value' => $type->value, 'label' => $type->label()],
            AttributeType::cases(),
        );
    }
}
