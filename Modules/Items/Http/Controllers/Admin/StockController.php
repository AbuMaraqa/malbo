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
use Modules\Items\Entities\Product;
use Modules\Items\Entities\StockMovement;
use Modules\Items\Enums\StockMovementType;
use Modules\Items\Http\Requests\Admin\StockAdjustRequest;
use Modules\Items\Http\Requests\Admin\StockReceiveRequest;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Repositories\Contracts\StockMovementRepositoryInterface;
use Modules\Items\Services\InventoryService;
use Modules\Items\Support\IntegerFilter;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The shared stock in the admin: the ledger of every movement, what runs low, goods that arrive
 * (INCOMING) and corrections by hand (ADJUST). Every change goes through InventoryService.
 */
class StockController extends Controller
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $movements,
        private readonly ProductRepositoryInterface $products,
        private readonly CategoryRepositoryInterface $categories,
        private readonly InventoryService $inventory,
    ) {}

    public function movements(): Response
    {
        return Inertia::render('Items/Stock/Movements', [
            'table' => AdminTable::for($this->movements->tableQuery())
                ->filters(
                    AllowedFilter::callback('search', fn (Builder $query, mixed $value) => $this->whereProduct($query, $value)),
                    AllowedFilter::callback('product', fn (Builder $query, mixed $value) => $this->whereProduct($query, $value)),
                    IntegerFilter::make('product_id'),
                    AdminFilter::exact('type'),
                    AllowedFilter::callback('order', fn (Builder $query, mixed $value) => $query->whereHas('order', fn (Builder $orders) => $orders
                        ->withTrashed()
                        ->whereLike('order_number', self::like($value), caseSensitive: false))),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('id', 'created_at', 'quantity', 'balance_after')
                ->rows(fn (StockMovement $movement) => [
                    'id' => $movement->id,
                    'created_at' => $movement->created_at?->format('Y-m-d H:i'),
                    'product_id' => $movement->product_id,
                    'product_name' => $movement->product ? (string) $movement->product->name : __('Deleted product'),
                    'sku' => $movement->product?->sku,
                    // A variant is edited on its parent's page.
                    'edit_product_id' => $movement->product?->parent_id ?? $movement->product_id,
                    'product_deleted' => ! $movement->product || $movement->product->trashed() || $movement->product->parent?->trashed(),
                    'type' => $movement->type->value,
                    'type_label' => $movement->type->getLabel(),
                    'type_tone' => $movement->type->tone(),
                    'quantity' => $movement->quantity,
                    'balance_after' => $movement->balance_after,
                    'order_id' => $movement->order_id,
                    'order_number' => $movement->order?->order_number,
                    'order_deleted' => $movement->order?->trashed() ?? false,
                    'note' => $movement->note,
                    'by' => $movement->createdBy?->name,
                ]),
            'types' => StockMovementType::options(),
        ]);
    }

    public function lowStock(): Response
    {
        return Inertia::render('Items/Stock/LowStock', [
            'table' => AdminTable::for($this->products->lowStockQuery()->with(['translations', 'parent.translations']))
                ->filters(
                    AllowedFilter::callback('search', fn (Builder $query, mixed $value) => $query->where(fn (Builder $query) => $query
                        ->whereLike('sku', self::like($value), caseSensitive: false)
                        ->orWhereHas('translations', fn (Builder $translations) => $translations->whereLike('name', self::like($value), caseSensitive: false))
                        ->orWhereHas('parent.translations', fn (Builder $translations) => $translations->whereLike('name', self::like($value), caseSensitive: false)))),
                    // A variant is in its parent's categories.
                    AllowedFilter::callback('category', function (Builder $query, mixed $value): void {
                        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);

                        if ($id !== false) {
                            $inCategory = fn (Builder $categories) => $categories->where($categories->qualifyColumn('id'), $id);
                            $query->where(fn (Builder $query) => $query->whereHas('categories', $inCategory)->orWhereHas('parent.categories', $inCategory));
                        }
                    }),
                    AllowedFilter::callback('out_of_stock', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $query->where('stock_quantity', '<=', 0)
                        : $query->where('stock_quantity', '>', 0)),
                )
                ->sorts('id', 'stock_quantity', 'low_stock_threshold')
                ->defaultSort('stock_quantity')
                ->rows(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => (string) $product->name,
                    'sku' => $product->sku,
                    'stock' => (int) $product->stock_quantity,
                    'threshold' => (int) $product->low_stock_threshold,
                    'backorder' => (bool) $product->allow_backorder,
                    'edit_product_id' => $product->parent_id ?? $product->id,
                ]),
            'categories' => collect($this->categories->options())->map(fn (string $name, int $id) => ['value' => $id, 'label' => $name])->values(),
        ]);
    }

    public function receiveForm(Request $request): Response
    {
        return Inertia::render('Items/Stock/Receive', ['selected' => $this->selected($request)]);
    }

    public function receive(StockReceiveRequest $request): RedirectResponse
    {
        $data = $request->validated();
        /** @var Product $product */
        $product = $this->products->findOrFail((int) $data['product_id']);

        $this->inventory->receive($product, (int) $data['quantity'], $data['note'] ?? null, $request->user()->id);

        return to_route('stock.receive')->with('success', __('Received: the stock of :name is now :count.', ['name' => (string) $product->name, 'count' => $product->stock_quantity]));
    }

    public function adjustForm(Request $request): Response
    {
        return Inertia::render('Items/Stock/Adjust', ['selected' => $this->selected($request)]);
    }

    public function adjust(StockAdjustRequest $request): RedirectResponse
    {
        $data = $request->validated();
        /** @var Product $product */
        $product = $this->products->findOrFail((int) $data['product_id']);

        $this->inventory->adjust($product, (int) $data['delta'], (string) $data['reason'], $request->user()->id);

        return to_route('stock.adjust')->with('success', __('Adjusted: the stock of :name is now :count.', ['name' => (string) $product->name, 'count' => $product->stock_quantity]));
    }

    /**
     * Products that hold stock (variants, and products without variants), by name or SKU, 50 at a time.
     */
    public function products(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        return response()->json($this->products->searchSellable($term)->map(fn (Product $product) => self::option($product))->values());
    }

    /**
     * The product named by `?product=` (a link from the low-stock page), when it holds stock.
     *
     * @return array{value: int, label: string, stock: int}|null
     */
    private function selected(Request $request): ?array
    {
        $id = filter_var($request->query('product'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);

        if ($id === false || ! $this->products->isSellable($id)) {
            return null;
        }

        /** @var Product|null $product */
        $product = $this->products->find($id);

        return $product ? self::option($product) : null;
    }

    /**
     * @return array{value: int, label: string, stock: int, incoming: int}
     */
    private static function option(Product $product): array
    {
        $label = (string) $product->name;

        return [
            'value' => $product->id,
            'label' => filled($product->sku) ? $label.' · '.$product->sku : $label,
            'stock' => (int) $product->stock_quantity,
            'incoming' => (int) $product->incoming_quantity,
        ];
    }

    private function whereProduct(Builder $query, mixed $value): void
    {
        $like = self::like($value);
        $named = fn (Builder $translations) => $translations->whereLike('name', $like, caseSensitive: false);

        $query->whereHas('product', fn (Builder $products) => $products->where(fn (Builder $products) => $products
            ->whereLike('sku', $like, caseSensitive: false)
            ->orWhereHas('translations', $named)
            ->orWhereHas('parent.translations', $named)));
    }

    private static function like(mixed $value): string
    {
        return '%'.addcslashes(trim((string) $value), '%_\\').'%';
    }
}
