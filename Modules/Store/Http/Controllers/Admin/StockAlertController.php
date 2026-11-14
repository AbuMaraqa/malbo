<?php

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Entities\AttributeValue;
use Modules\Store\Entities\StockAlert;
use Modules\Store\Repositories\Contracts\StockAlertRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * "Notify me when available" requests, one row per item: how many buyers wait for it and how many
 * were told. What to restock first.
 */
class StockAlertController extends Controller
{
    public function __construct(private readonly StockAlertRepositoryInterface $alerts) {}

    public function index(): Response
    {
        return Inertia::render('Store/StockAlert/Index', [
            'table' => AdminTable::for($this->alerts->groupedByProductQuery())
                ->filters(
                    AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                        $term = '%'.addcslashes(trim((string) $value), '%_\\').'%';

                        $query->whereHas('product', fn (Builder $product) => $product->withTrashed()->where(fn (Builder $match) => $match
                            ->whereLike('sku', $term, caseSensitive: false)
                            ->orWhereHas('translations', fn (Builder $name) => $name->whereLike('name', $term, caseSensitive: false))
                            ->orWhereHas('parent', fn (Builder $parent) => $parent->withTrashed()->whereHas('translations', fn (Builder $name) => $name->whereLike('name', $term, caseSensitive: false)))));
                    }),
                    AllowedFilter::callback('waiting', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $query->havingRaw('count(*) filter (where notified_at is null) > 0')
                        : $query->havingRaw('count(*) filter (where notified_at is null) = 0')),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts(
                    'waiting_count',
                    'notified_count',
                    'last_requested_at',
                )
                ->defaultSort('-waiting_count')
                ->rows(function (StockAlert $row) {
                    $product = $row->product;
                    $parent = $product?->parent ?? $product;

                    return [
                        'id' => (int) $row->product_id,
                        'product_id' => (int) $row->product_id,
                        'name' => (string) ($parent?->name ?? ''),
                        'options' => $product?->parent_id ? $product->attributeValues->sortBy('attribute_id')->map(fn (AttributeValue $value) => (string) $value->name)->implode(' / ') : null,
                        'sku' => $product?->sku,
                        'stock' => $product?->manage_stock ? (int) $product->stock_quantity : null,
                        'edit_url' => $parent && ! $parent->trashed() ? route('products.edit', ['product' => $parent->id]) : null,
                        'waiting' => (int) $row->waiting_count,
                        'notified' => (int) $row->notified_count,
                        'last_requested_at' => $row->last_requested_at ? Carbon::parse($row->last_requested_at)->diffForHumans() : null,
                    ];
                }),
        ]);
    }
}
