<?php

namespace Modules\Items\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Http\Requests\Admin\ProductRequest;
use Modules\Items\Repositories\Contracts\AddonRepositoryInterface;
use Modules\Items\Repositories\Contracts\AttributeRepositoryInterface;
use Modules\Items\Repositories\Contracts\BrandRepositoryInterface;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\LabelRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Repositories\Contracts\TagRepositoryInterface;
use Modules\Items\Services\ItemsPdfService;
use Modules\Items\Services\ProductService;
use Modules\Items\Support\IntegerFilter;
use Modules\Items\Support\ProductSeo;
use Modules\Items\Support\RecordInfo;
use Modules\Items\Support\TranslatedSort;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class ProductController extends Controller
{
    /** Labels one PDF may hold: enough for a shelf, not enough to exhaust the server. */
    private const MAX_LABELS = 1000;

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductService $productService,
        private readonly CategoryRepositoryInterface $categories,
        private readonly BrandRepositoryInterface $brands,
        private readonly BranchRepositoryInterface $branches,
        private readonly TagRepositoryInterface $tags,
        private readonly LabelRepositoryInterface $labels,
        private readonly AttributeRepositoryInterface $attributes,
        private readonly AddonRepositoryInterface $addons,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Items/Product/Index', [
            'table' => AdminTable::for($this->products->tableQuery())
                ->search(['sku', 'slug', 'barcode'])
                ->filters(
                    AdminFilter::translated('name'),
                    IntegerFilter::make('status'),
                    IntegerFilter::make('is_active'),
                    AdminFilter::relation('category_id', 'categories'),
                    IntegerFilter::make('brand_id'),
                    AdminFilter::boolean('is_featured'),
                    AllowedFilter::exact('stock_status'),
                    ...AdminFilter::numberRange('base_price'),
                    ...AdminFilter::numberRange('sale_price'),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts(AllowedSort::custom('name', new TranslatedSort), 'slug', 'status', 'is_active', 'base_price', 'sale_price', 'discount', 'id')
                ->rows(fn (Product $product) => [
                    'id' => $product->id,
                    'image' => $product->getImageAttribute(),
                    'name' => (string) $product->name,
                    'slug' => $product->slug,
                    'description' => $product->description ? strip_tags($product->description) : null,
                    'categories' => $product->categories->pluck('name')->join(', '),
                    'status' => $product->status?->value,
                    'is_active' => (int) $product->is_active === ProductActive::IS_ACTIVE->value,
                    'base_price' => $product->base_price,
                    'sale_price' => $product->sale_price,
                    'discount' => $product->discount,
                    'locales' => $product->translations->pluck('locale'),
                ]),
            'statuses' => $this->statuses(),
            'categories' => fn () => self::options($this->categories->options()),
            'brands' => fn () => $this->brands->options(),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Items/Product/Add', $this->formProps());
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        try {
            $this->productService->create($request->forService(), app()->getLocale(), $request->user()->id);
        } catch (UniqueConstraintViolationException) {
            // The same form sent twice at once: both passed validation, the second lost the race.
            throw ValidationException::withMessages(['slug' => __('validation.unique', ['attribute' => __('Slug')])]);
        }

        return to_route('products.index')->with('success', __('Product created successfully'));
    }

    public function edit(int $product): Response
    {
        $model = $this->products->forEdit($product);

        return Inertia::render('Items/Product/Edit', [
            ...$this->formProps(),
            'product' => $this->formState($model),
        ]);
    }

    public function update(ProductRequest $request, int $product): RedirectResponse
    {
        /** @var Product $model */
        $model = $this->products->findOrFail($product);

        $this->productService->update($model, $request->forService(), app()->getLocale(), $request->user()->id);

        return to_route('products.index')->with('success', __('Product updated successfully'));
    }

    public function status(Request $request, int $product): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(ProductStatus::formOptions()))]])['status'];

        /** @var Product $model */
        $model = $this->products->findOrFail($product);

        $this->productService->setStatus($model, (int) $status);

        return back()->with('success', __('Product updated successfully'));
    }

    public function active(Request $request, int $product): RedirectResponse
    {
        $active = $request->validate(['active' => ['required', 'boolean']])['active'];

        /** @var Product $model */
        $model = $this->products->findOrFail($product);

        $this->productService->setActive($model, (bool) $active);

        return back()->with('success', $active ? __('Product activated successfully') : __('Product deactivated successfully'));
    }

    public function destroy(int $product): RedirectResponse
    {
        /** @var Product $model */
        $model = $this->products->findOrFail($product);

        $this->productService->forceDelete($model);

        return back()->with('success', __('Product deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->productService->forceDeleteMany($this->products->query()->whereKey($ids)->get());

        return back()->with('success', __('Products deleted successfully'));
    }

    public function info(int $product): JsonResponse
    {
        return response()->json(RecordInfo::of($this->products->findOrFail($product)));
    }

    /**
     * The rows of the barcode dialog: the product and its variations, with a preview of each code.
     */
    public function barcodes(ItemsPdfService $pdf, int $product): JsonResponse
    {
        $model = $this->products->withVariations($product);

        return response()->json([
            'rows' => collect([$model, ...$model->variations])->map(fn (Product $row) => [
                'id' => $row->id,
                'name' => (string) $row->name,
                'code' => $pdf->labelCode($row),
                'svg' => $pdf->generateBarcodeSvg($pdf->labelCode($row), 'C128', 1, 40),
            ])->values(),
        ]);
    }

    public function barcodesPdf(Request $request, ItemsPdfService $pdf, int $product): HttpResponse
    {
        $quantities = $request->validate([
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['integer', 'min:0', 'max:'.self::MAX_LABELS],
        ])['quantities'] ?? [];

        $model = $this->products->withVariations($product);
        $total = collect([$model, ...$model->variations])->sum(fn (Product $row) => (int) ($quantities[$row->id] ?? 1));

        abort_if($total < 1 || $total > self::MAX_LABELS, 422, __('Choose between 1 and :max labels.', ['max' => self::MAX_LABELS]));

        return $pdf->generateBarcodeLabels($model, $quantities);
    }

    public function branchPricings(int $product): Response
    {
        $model = $this->products->withVariations($product);
        $branches = $this->branches->query()->with('translations')->orderBy('id')->get();
        $rows = collect([$model, ...$model->variations]);

        $saved = $this->products->branchPrices($rows->pluck('id'));

        return Inertia::render('Items/Product/BranchPricings', [
            'product' => ['id' => $model->id, 'name' => (string) $model->name],
            'rows' => $rows->map(fn (Product $row) => ['id' => $row->id, 'name' => (string) $row->name])->values(),
            'branches' => $branches->map(fn ($branch) => ['id' => $branch->id, 'name' => (string) $branch->name])->values(),
            'prices' => $rows->mapWithKeys(fn (Product $row) => [
                $row->id => $branches->mapWithKeys(fn ($branch) => [
                    $branch->id => $saved->get("{$row->id}:{$branch->id}")?->price ?? '',
                ]),
            ]),
        ]);
    }

    public function saveBranchPricings(Request $request, int $product): RedirectResponse
    {
        $prices = $request->validate([
            'prices' => ['required', 'array'],
            'prices.*' => ['array'],
            'prices.*.*' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ], [], ['prices.*.*' => __('Price')])['prices'];

        $this->productService->saveBranchPrices($this->products->withVariations($product), $prices);

        return back()->with('success', __('Prices saved successfully'));
    }

    /**
     * The lists both forms choose from.
     *
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'statuses' => $this->statuses(),
            'categories' => self::options($this->categories->options()),
            'brands' => $this->brands->options(),
            'branches' => $this->branches->query()->with('translations')->orderBy('id')->get()
                ->map(fn ($branch) => ['value' => $branch->id, 'label' => (string) $branch->name])->values(),
            'tagSuggestions' => $this->tags->names(),
            'labelSuggestions' => $this->labels->names(),
            'attributes' => $this->attributes->withValues()->map(fn (Attribute $attribute) => [
                'id' => $attribute->id,
                'name' => (string) $attribute->name,
                'values' => $attribute->attributeValues->map(fn (AttributeValue $value) => [
                    'id' => $value->id,
                    'name' => (string) $value->name,
                    'color' => $value->color_code,
                ])->values(),
            ])->values(),
            'addons' => $this->addons->withOptions()->map(fn (Addon $addon) => [
                'id' => $addon->id,
                'name' => (string) $addon->name,
                'options' => $addon->addonOptions->map(fn (AddonOption $option) => [
                    'id' => $option->id,
                    'name' => (string) $option->name,
                    'price' => $option->price,
                ])->values(),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formState(Product $product): array
    {
        $locale = app()->getLocale();
        $overrides = $this->products->addonOptionOverrides($product->addons->pluck('pivot.id'));

        return [
            'id' => $product->id,
            ...$this->rowState($product, $locale),
            'status' => $product->status?->value,
            'brand_id' => $product->brand_id,
            'tags' => $product->tags->pluck('name')->filter()->values(),
            'labels' => $product->labels->pluck('name')->filter()->values(),
            'branches' => $product->branches->modelKeys(),
            'addons' => $product->addons->map(fn (Addon $addon) => [
                'addon_id' => $addon->id,
                'is_required' => (bool) $addon->pivot->is_required,
                'min_selection' => $addon->pivot->min_selection,
                'max_selection' => $addon->pivot->max_selection,
                'max_quantity_per_option' => $addon->pivot->max_quantity_per_option,
                'allowed_options' => $overrides->get($addon->pivot->id, collect())->map(fn (object $row) => [
                    'addon_option_id' => $row->addon_option_id,
                    'price' => $row->price,
                    'is_default' => (bool) $row->is_default,
                ])->values(),
            ])->values(),
            'variations' => $product->variations->map(fn (Product $variation) => [
                'id' => $variation->id,
                ...$this->rowState($variation, $locale),
                'attribute_values' => $variation->attributeValues->modelKeys(),
            ])->values(),
            // Which values each attribute has selected, from the saved variations.
            'attributes' => $product->variations->flatMap->attributeValues
                ->groupBy('attribute_id')
                ->map(fn ($values) => $values->pluck('id')->unique()->values()),
        ];
    }

    /**
     * The fields a product and a variation share, in the locale being edited.
     *
     * @return array<string, mixed>
     */
    private function rowState(Product $product, string $locale): array
    {
        $translation = $product->translate($locale, false);
        $images = fn (string $collection) => $product->getMedia($collection)
            ->map(fn (Media $media) => ['id' => $media->id, 'url' => $media->getUrl()])->values();

        return [
            'name' => $translation?->name ?? '',
            'slug' => $product->slug,
            'short_description' => $translation?->short_description ?? '',
            'description' => $translation?->description ?? '',
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'weight' => $product->weight,
            'is_featured' => (bool) $product->is_featured,
            'is_digital' => (bool) $product->is_digital,
            'base_price' => $product->base_price,
            'sale_price' => $product->sale_price,
            'cost_price' => $product->cost_price,
            'wholesale_price' => $product->wholesale_price,
            'discount_type' => $product->discount_type,
            'discount_value' => $product->discount_value,
            'discount_start_date' => $product->discount_start_date ? substr((string) $product->discount_start_date, 0, 10) : null,
            'discount_end_date' => $product->discount_end_date ? substr((string) $product->discount_end_date, 0, 10) : null,
            'manage_stock' => (bool) $product->manage_stock,
            'stock_quantity' => $product->stock_quantity,
            'low_stock_threshold' => $product->low_stock_threshold,
            'stock_status' => $product->stock_status,
            'allow_backorder' => (bool) $product->allow_backorder,
            'max_quantity_per_order' => $product->max_quantity_per_order,
            'category_id' => $product->categories->modelKeys(),
            'images' => $images('product'),
            'gallery_images' => $images('product_gallery'),
            ...ProductSeo::unpack($product->meta_data, $locale),
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function statuses(): array
    {
        return self::options(ProductStatus::formOptions());
    }

    /**
     * @param  array<int, string>  $options
     * @return list<array{value: int, label: string}>
     */
    private static function options(array $options): array
    {
        return collect($options)->map(fn (string $label, int $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
