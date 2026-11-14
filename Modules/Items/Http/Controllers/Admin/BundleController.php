<?php

namespace Modules\Items\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Entities\BundleComponent;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\BundleType;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Http\Requests\Admin\BundleRequest;
use Modules\Items\Repositories\Contracts\BundleRepositoryInterface;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Services\BundleService;
use Modules\Items\Support\IntegerFilter;
use Modules\Items\Support\TranslatedSort;
use Modules\Store\Services\PricingService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Sets and bundle offers: their list, and a form of their own (texts in the current locale, images,
 * categories, price, components). They are sold on the storefront only.
 */
class BundleController extends Controller
{
    public function __construct(
        private readonly BundleRepositoryInterface $bundles,
        private readonly BundleService $bundleService,
        private readonly CategoryRepositoryInterface $categories,
        private readonly PricingService $pricing,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Items/Bundle/Index', [
            'table' => AdminTable::for($this->bundles->tableQuery())
                ->search(['sku', 'slug'])
                ->filters(
                    AdminFilter::translated('name'),
                    AdminFilter::exact('bundle_type'),
                    IntegerFilter::make('status'),
                    IntegerFilter::make('is_active'),
                    ...AdminFilter::numberRange('base_price'),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts(AllowedSort::custom('name', new TranslatedSort), 'base_price', 'status', 'created_at', 'id')
                ->rows(fn (Product $bundle) => [
                    'id' => $bundle->id,
                    'image' => $bundle->getFirstMediaUrl('product') ?: null,
                    'name' => (string) $bundle->name,
                    'type' => $bundle->bundle_type,
                    'type_label' => BundleType::tryFrom((string) $bundle->bundle_type)?->label(),
                    'components_count' => (int) $bundle->bundle_components_count,
                    'price' => $this->pricing->for($bundle)->format(),
                    'percent' => $bundle->bundle_discount_percent !== null ? (float) $bundle->bundle_discount_percent : null,
                    'categories' => $bundle->categories->pluck('name')->join(', '),
                    'published' => $bundle->status === ProductStatus::PUBLISHED,
                    'is_active' => (int) $bundle->is_active === ProductActive::IS_ACTIVE->value,
                    'url' => route('store.product', ['slug' => $bundle->slug]),
                    'created_at' => $bundle->created_at?->toDateString(),
                ]),
            'types' => BundleType::options(),
            'statuses' => self::statuses(),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Items/Bundle/Add', $this->formProps());
    }

    public function store(BundleRequest $request): RedirectResponse
    {
        $this->bundleService->create($request->validated(), app()->getLocale(), $request->user()->id);

        return to_route('bundles.index')->with('success', __('Saved Successfully'));
    }

    public function edit(int $bundle): Response
    {
        $model = $this->bundles->forEdit($bundle);
        $translation = $model->translate(app()->getLocale(), false);

        return Inertia::render('Items/Bundle/Edit', [
            ...$this->formProps(),
            'bundle' => [
                'id' => $model->id,
                'bundle_type' => $model->bundle_type,
                'name' => $translation?->name ?? '',
                'short_description' => $translation?->short_description ?? '',
                'description' => $translation?->description ?? '',
                'slug' => $model->slug,
                'sku' => $model->sku,
                'status' => $model->status?->value,
                'is_active' => (int) $model->is_active === ProductActive::IS_ACTIVE->value,
                'is_new' => (bool) $model->is_new,
                'is_featured' => (bool) $model->is_featured,
                'category_id' => $model->categories->modelKeys(),
                'pricing' => $model->bundle_discount_percent !== null ? 'percent' : 'fixed',
                'base_price' => $model->base_price,
                'sale_price' => $model->sale_price,
                'bundle_discount_percent' => $model->bundle_discount_percent,
                'max_quantity_per_order' => $model->max_quantity_per_order,
                'components' => $model->bundleComponents->filter(fn (BundleComponent $component) => $component->product_id !== null)
                    ->map(fn (BundleComponent $component) => ['id' => $component->id, 'product_id' => $component->product_id, 'quantity' => $component->quantity])->values(),
                'images' => $model->getMedia('product')->map(fn (Media $media) => ['id' => $media->id, 'url' => $media->getUrl()])->values(),
                'gallery_images' => $model->getMedia('product_gallery')->map(fn (Media $media) => ['id' => $media->id, 'url' => $media->getUrl()])->values(),
            ],
            'selectedComponents' => $model->bundleComponents->map(fn (BundleComponent $component) => $component->product)->filter()
                ->map(fn (Product $product) => self::componentOption($product))->values(),
            'viewUrl' => route('store.product', ['slug' => $model->slug]),
        ]);
    }

    public function update(BundleRequest $request, int $bundle): RedirectResponse
    {
        $this->bundleService->update($this->bundles->forEdit($bundle), $request->validated(), app()->getLocale());

        return to_route('bundles.index')->with('success', __('Saved Successfully'));
    }

    public function destroy(int $bundle): RedirectResponse
    {
        $this->bundleService->delete($this->bundles->forEdit($bundle));

        return back()->with('success', __('Deleted successfully'));
    }

    /**
     * What a component may be (BundleRepositoryInterface::componentOptions), 50 at a time.
     */
    public function lookup(Request $request): JsonResponse
    {
        return response()->json($this->bundles->componentOptions(trim((string) $request->query('q', '')))
            ->map(fn (Product $product) => self::componentOption($product))->values());
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'types' => BundleType::options(),
            'statuses' => self::statuses(),
            'categories' => collect($this->categories->options())->map(fn (string $label, int $value) => ['value' => $value, 'label' => $label])->values(),
        ];
    }

    /**
     * A product as the component picker shows it: a variant with its options, a product with
     * variants marked as "the buyer chooses".
     *
     * @return array{value: int, label: string}
     */
    private static function componentOption(Product $product): array
    {
        if ($product->parent_id) {
            $values = $product->attributeValues->map(fn ($value) => (string) $value->name)->filter()->implode(' / ');

            return ['value' => $product->id, 'label' => trim((string) ($product->parent?->name ?? $product->name).($values !== '' ? ' — '.$values : '')).' · '.$product->sku];
        }

        $label = (string) $product->name;

        return ['value' => $product->id, 'label' => $product->variations->isNotEmpty() ? __(':name (the buyer chooses the size)', ['name' => $label]) : $label];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private static function statuses(): array
    {
        return collect(ProductStatus::formOptions())->map(fn (string $label, int $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
