<?php

namespace Modules\Wholesale\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Product;
use Modules\Wholesale\Repositories\Contracts\PreorderRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Pre-orders: per product (a variant, or a product without variants), the pieces open B2B orders
 * reserved from its incoming goods, the orders, and when the goods are expected.
 */
class PreorderController extends Controller
{
    public function __construct(private readonly PreorderRepositoryInterface $preorders) {}

    public function index(): Response
    {
        $table = AdminTable::for($this->preorders->tableQuery())
            ->filters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $term = '%'.addcslashes(trim((string) $value), '%_\\').'%';
                    $query->where(fn (Builder $query) => $query
                        ->whereLike($query->qualifyColumn('sku'), $term, caseSensitive: false)
                        ->orWhereHas('translations', fn (Builder $names) => $names->whereLike('name', $term, caseSensitive: false))
                        ->orWhereHas('parent.translations', fn (Builder $names) => $names->whereLike('name', $term, caseSensitive: false)));
                }),
                // Received: none of its incoming goods are still on their way.
                AllowedFilter::callback('received', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ? $query->where($query->qualifyColumn('incoming_quantity'), '<=', 0)
                    : $query->where($query->qualifyColumn('incoming_quantity'), '>', 0)),
                ...AdminFilter::dateRange('expected_at', 'incoming_expected_at'),
            )
            ->sorts('incoming_expected_at', 'preordered', 'id')
            ->defaultSort('incoming_expected_at')
            ->rows(fn (Product $product) => [
                'id' => $product->id,
                'name' => (string) (($product->parent ?? $product)->name ?? $product->sku),
                'options' => $product->attributeValues->sortBy('attribute_id')->map(fn (AttributeValue $value) => (string) $value->name)->implode(' / ') ?: null,
                'sku' => $product->sku,
                'preordered' => (int) $product->getAttribute('preordered'),
                'orders_count' => (int) $product->getAttribute('orders_count'),
                'incoming_quantity' => (int) $product->incoming_quantity,
                'expected_at' => $product->incoming_expected_at?->toDateString(),
                'stock' => (int) $product->stock_quantity,
                'prices_url' => route('wholesale-prices.edit', $product->parent_id ?: $product->id),
            ]);

        $orders = $this->preorders->ordersFor(array_column($table['data'], 'id'));

        foreach ($table['data'] as &$row) {
            $row['orders'] = array_map(fn (array $order) => [...$order, 'url' => route('orders.edit', $order['id'])], $orders[$row['id']] ?? []);
        }
        unset($row);

        return Inertia::render('Wholesale/Preorder/Index', ['table' => $table]);
    }
}
