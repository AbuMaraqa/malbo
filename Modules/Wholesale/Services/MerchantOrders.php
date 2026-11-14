<?php

namespace Modules\Wholesale\Services;

use Modules\Items\Entities\Order;
use Modules\Store\Services\OrderTracking;
use Modules\Store\Support\Price;
use Modules\Wholesale\Repositories\Contracts\WholesaleOrderRepositoryInterface;

/**
 * A merchant's wholesale orders and one order's detail (what tracking shows: lines as sold, totals,
 * the status timeline; never an internal note).
 */
class MerchantOrders
{
    public const PER_PAGE = 10;

    public function __construct(
        private readonly WholesaleOrderRepositoryInterface $orders,
        private readonly OrderTracking $tracking,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, prev_url: ?string, next_url: ?string, total: int}
     */
    public function page(int $userId): array
    {
        $page = $this->orders->forMerchant($userId, self::PER_PAGE);

        return [
            'data' => collect($page->items())->map(fn (Order $order) => [
                'number' => $order->order_number,
                'placed_at' => $order->created_at?->format('Y-m-d'),
                'status' => ['key' => strtolower($order->status->name), 'label' => $order->status->getLabel()],
                'pieces' => (int) $order->items_sum_quantity,
                'total_label' => (new Price((float) $order->total))->format(),
                'payment' => $order->payment_method?->getLabel(),
                'url' => route('store.wholesale.orders.show', ['number' => $order->order_number]),
            ])->values()->all(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'prev_url' => $page->previousPageUrl(),
            'next_url' => $page->nextPageUrl(),
            'total' => $page->total(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(int $userId, string $number): ?array
    {
        $order = $this->orders->findForMerchant($userId, $number);

        if ($order === null) {
            return null;
        }

        return [
            ...$this->tracking->result($order),
            'payment' => $order->payment_method?->getLabel(),
            'delivery_address' => $order->delivery_address,
            'notes' => $order->notes,
            'reorder_url' => route('store.wholesale.orders.reorder', ['number' => $order->order_number]),
        ];
    }
}
