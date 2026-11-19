<?php

namespace Modules\Wholesale\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Support\TranslatedSort;
use Modules\Store\Support\Price;
use Modules\Wholesale\Entities\QuantityBreak;
use Modules\Wholesale\Entities\Tier;
use Modules\Wholesale\Entities\TierPrice;
use Modules\Wholesale\Http\Requests\Admin\IncomingRequest;
use Modules\Wholesale\Http\Requests\Admin\ProductPriceRequest;
use Modules\Wholesale\Repositories\Contracts\TierRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesalePriceRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesaleProductRepositoryInterface;
use Modules\Wholesale\Services\WholesalePriceService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Products' wholesale terms: the list, and a product's page (each item's price per tier, the
 * quantity breaks, sold by the piece or in size runs). The base wholesale price stays in the
 * product form.
 */
class ProductPriceController extends Controller
{
    public function __construct(
        private readonly WholesaleProductRepositoryInterface $products,
        private readonly WholesalePriceRepositoryInterface $prices,
        private readonly TierRepositoryInterface $tiers,
        private readonly WholesalePriceService $priceService,
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    public function index(): Response
    {
        $prices = (new TierPrice)->getTable();
        $breaks = (new QuantityBreak)->getTable();

        return Inertia::render('Wholesale/ProductPrice/Index', [
            'table' => AdminTable::for($this->products->adminTableQuery())
                ->search(['sku'])
                ->filters(
                    AdminFilter::translated('name'),
                    AdminFilter::relation('category_id', 'categories'),
                    AdminFilter::boolean('wholesale_by_piece'),
                    AllowedFilter::callback('has_wholesale_price', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $query->where(fn (Builder $query) => $query->where('wholesale_price', '>', 0)->orWhereHas('variations', fn (Builder $variations) => $variations->where('wholesale_price', '>', 0)))
                        : $query->where(fn (Builder $query) => $query->whereNull('wholesale_price')->orWhere('wholesale_price', '<=', 0))->whereDoesntHave('variations', fn (Builder $variations) => $variations->where('wholesale_price', '>', 0))),
                    AllowedFilter::callback('has_breaks', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $query->whereIn('id', fn ($sub) => $sub->select('product_id')->from($breaks))
                        : $query->whereNotIn('id', fn ($sub) => $sub->select('product_id')->from($breaks))),
                )
                ->sorts(AllowedSort::custom('name', new TranslatedSort), 'wholesale_price', 'id')
                ->rows(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => (string) $product->name,
                    'sku' => $product->sku,
                    'categories' => $product->categories->pluck('name')->join(', '),
                    'wholesale_price' => (float) $product->wholesale_price > 0 ? (new Price((float) $product->wholesale_price))->format() : null,
                    'variations_count' => (int) $product->variations_count,
                    'by_piece' => (bool) ($product->wholesale_by_piece ?? true),
                ]),
            'categories' => fn () => collect($this->categories->options())->map(fn ($name, $id) => ['value' => (int) $id, 'label' => (string) $name])->values()->all(),
        ]);
    }

    public function edit(int $product): Response
    {
        $model = $this->products->forPriceEdit($product);
        $items = $model->variations->isEmpty() ? $model->newCollection([$model]) : $model->variations->sortBy('id')->values();
        $saved = $this->prices->allTierPrices($items->modelKeys());

        return Inertia::render('Wholesale/ProductPrice/Edit', [
            'product' => [
                'id' => $model->id,
                'name' => (string) $model->name,
                'sku' => $model->sku,
                'wholesale_price' => (float) $model->wholesale_price > 0 ? (float) $model->wholesale_price : null,
                'by_piece' => (bool) ($model->wholesale_by_piece ?? true),
                'edit_url' => route('products.edit', $model->id),
            ],
            'items' => $items->map(fn (Product $item) => [
                'id' => $item->id,
                'name' => $item->id === $model->id ? (string) $model->name : ($item->attributeValues->sortBy('attribute_id')->map(fn (AttributeValue $value) => (string) $value->name)->implode(' / ') ?: (string) $item->name),
                'sku' => $item->sku,
                'active' => (int) $item->is_active === 1,
                // Empty on a variant: it falls back to the product's.
                'wholesale_price' => (float) $item->wholesale_price > 0 ? (float) $item->wholesale_price : null,
                // Incoming goods merchants may reserve (InventoryService); below 0, the stock is reserved.
                'stock_quantity' => (int) $item->stock_quantity,
                'manage_stock' => (bool) $item->manage_stock,
                'incoming_quantity' => (int) $item->incoming_quantity,
                'incoming_expected_at' => $item->incoming_expected_at?->toDateString(),
            ])->values()->all(),
            'tiers' => $this->tiers->ordered()->map(fn (Tier $tier) => [
                'id' => $tier->id,
                'name' => (string) $tier->name,
                'discount_percent' => (float) $tier->discount_percent,
                'is_default' => (bool) $tier->is_default,
            ])->values()->all(),
            'prices' => (object) $saved->groupBy('product_id')->map(fn ($rows) => (object) $rows->mapWithKeys(fn (TierPrice $row) => [$row->tier_id => (float) $row->price])->all())->all(),
            'breaks' => $this->prices->breaksOf($model->id)->map(fn (QuantityBreak $break) => [
                'min_quantity' => (int) $break->min_quantity,
                'discount_percent' => (float) $break->discount_percent,
            ])->values()->all(),
        ]);
    }

    public function update(ProductPriceRequest $request, int $product): RedirectResponse
    {
        $this->priceService->save($this->products->forPriceEdit($product), $request->validated());

        return back()->with('success', __('Wholesale prices saved'));
    }

    /**
     * The incoming goods of the product's items, which merchants may pre-order.
     */
    public function incoming(IncomingRequest $request, int $product): RedirectResponse
    {
        $this->priceService->saveIncoming($this->products->forPriceEdit($product), $request->validated()['incoming'], (int) $request->user()->id);

        return back()->with('success', __('Incoming goods saved'));
    }
}
