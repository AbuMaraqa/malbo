<?php

namespace Modules\Items\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Entities\Offer;
use Modules\Items\Enums\OfferType;
use Modules\Items\Http\Requests\Admin\OfferRequest;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\OfferRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Services\OfferService;
use Modules\Items\Support\IntegerFilter;
use Modules\Items\Support\RecordInfo;
use Modules\Items\Support\SubmitOnce;
use Modules\Items\Support\TranslatedSort;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class OfferController extends Controller
{
    public function __construct(
        private readonly OfferRepositoryInterface $offers,
        private readonly OfferService $offerService,
        private readonly BranchRepositoryInterface $branches,
        private readonly CategoryRepositoryInterface $categories,
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Items/Offer/Index', [
            'table' => AdminTable::for($this->offers->tableQuery())
                ->search(['code'])
                ->filters(
                    AdminFilter::translated('name'),
                    AdminFilter::text('code'),
                    IntegerFilter::make('type'),
                    AdminFilter::boolean('is_active'),
                    IntegerFilter::make('branch_id'),
                    AllowedFilter::callback('offerable_model', function (Builder $query, mixed $value): void {
                        if ($type = OfferService::TARGETS[(string) $value] ?? null) {
                            $query->where('offerable_type', $type);
                        }
                    }),
                    ...AdminFilter::numberRange('value'),
                    ...AdminFilter::dateRange('start_date'),
                    ...AdminFilter::dateRange('end_date'),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts(AllowedSort::custom('name', new TranslatedSort), 'type', 'value', 'is_active', 'start_date', 'end_date', 'id')
                ->rows(fn (Offer $offer) => [
                    'id' => $offer->id,
                    'image' => $offer->getImageAttribute(),
                    'name' => (string) $offer->name,
                    'branch' => $offer->branch?->name,
                    'description' => $offer->description,
                    'offerable_type' => array_search($offer->offerable_type, OfferService::TARGETS, true) ?: null,
                    'offerable_id' => $offer->offerable_id,
                    'offerable' => $offer->offerable?->name,
                    'is_active' => (bool) $offer->is_active,
                    'type' => $offer->type?->getLabel(),
                    'value' => $offer->value,
                    'code' => $offer->code,
                    'start_date' => $offer->start_date?->format('Y-m-d H:i'),
                    'end_date' => $offer->end_date?->format('Y-m-d H:i'),
                ]),
            'types' => $this->types(),
            'branches' => fn () => $this->branchOptions(),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Items/Offer/Add', [...$this->formProps(), 'submitToken' => (string) Str::uuid()]);
    }

    public function store(OfferRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $created = SubmitOnce::run('offers', $data['submit_token'], fn () => $this->offerService->create($data, app()->getLocale(), $request->user()->id));

        return to_route('offers.index')->with('success', $created ? __('Offer created successfully') : __('This form was already saved'));
    }

    public function edit(int $offer): Response
    {
        $model = $this->offers->findForForm($offer);
        $locale = app()->getLocale();
        $media = $model->getFirstMedia('offer');

        return Inertia::render('Items/Offer/Edit', [
            ...$this->formProps(),
            'offer' => [
                'id' => $model->id,
                'name' => $model->translate($locale, false)?->name ?? '',
                'description' => $model->translate($locale, false)?->description ?? '',
                'branch_id' => $model->branch_id,
                'offerable_model' => array_search($model->offerable_type, OfferService::TARGETS, true) ?: null,
                'offerable_id' => $model->offerable_id,
                'type' => $model->type?->value,
                'value' => $model->value,
                'code' => $model->code,
                'min_purchase_amount' => $model->min_purchase_amount,
                'usage_limit' => $model->usage_limit,
                'usage_limit_per_user' => $model->usage_limit_per_user,
                'start_date' => $model->start_date?->format('Y-m-d\TH:i'),
                'end_date' => $model->end_date?->format('Y-m-d\TH:i'),
                'is_active' => (bool) $model->is_active,
            ],
            'images' => $media ? [['id' => $media->id, 'url' => $media->getUrl()]] : [],
        ]);
    }

    public function update(OfferRequest $request, int $offer): RedirectResponse
    {
        /** @var Offer $model */
        $model = $this->offers->findOrFail($offer);

        $this->offerService->update($model, $request->validated(), app()->getLocale());

        return to_route('offers.index')->with('success', __('Offer updated successfully'));
    }

    public function destroy(int $offer): RedirectResponse
    {
        /** @var Offer $model */
        $model = $this->offers->findOrFail($offer);

        $this->offerService->delete($model);

        return back()->with('success', __('Offer deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->offerService->deleteMany($ids);

        return back()->with('success', __('Offers deleted successfully'));
    }

    public function info(int $offer): JsonResponse
    {
        return response()->json(RecordInfo::of($this->offers->findOrFail($offer)));
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'types' => $this->types(),
            'branches' => $this->branchOptions(),
            'categories' => collect($this->categories->options())->map(fn (string $label, int $value) => ['value' => $value, 'label' => $label])->values(),
            'products' => $this->products->parentOptionsForOffers(),
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function types(): array
    {
        return array_map(fn (OfferType $type) => ['value' => $type->value, 'label' => $type->getLabel()], OfferType::cases());
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{value: int, label: string}>
     */
    private function branchOptions(): \Illuminate\Support\Collection
    {
        return $this->branches->query()->with('translations')->orderBy('id')->get()
            ->map(fn ($branch) => ['value' => $branch->id, 'label' => (string) $branch->name])->values();
    }
}
