<?php

namespace Modules\Items\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Shipment;
use Modules\Items\Enums\ShipmentStatus;
use Modules\Items\Http\Requests\Admin\ShipmentRequest;
use Modules\Items\Http\Requests\Admin\ShipmentStatusRequest;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Repositories\Contracts\ShipmentRepositoryInterface;
use Modules\Items\Services\Couriers\CourierRegistry;
use Modules\Items\Services\ShipmentService;
use Modules\Items\Support\ShipmentPresenter;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Shipments: the list, and the order page's card that makes one and moves it along. Every change
 * goes through ShipmentService, which moves the order through OrderService.
 */
class ShipmentController extends Controller
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipments,
        private readonly OrderRepositoryInterface $orders,
        private readonly ShipmentService $service,
        private readonly ShipmentPresenter $presenter,
        private readonly CourierRegistry $couriers,
    ) {}

    public function index(): Response
    {
        $like = fn (mixed $value) => '%'.addcslashes(trim((string) $value), '%_\\').'%';

        return Inertia::render('Items/Shipment/Index', [
            'table' => AdminTable::for($this->shipments->tableQuery())
                ->filters(
                    AllowedFilter::callback('search', fn (Builder $query, mixed $value) => $query->where(fn (Builder $query) => $query
                        ->whereLike('tracking_number', $like($value), caseSensitive: false)
                        ->orWhereHas('order', fn (Builder $orders) => $orders->whereLike('order_number', $like($value), caseSensitive: false)))),
                    AdminFilter::exact('status'),
                    AdminFilter::exact('courier'),
                    AllowedFilter::callback('order_number', fn (Builder $query, mixed $value) => $query->whereHas('order', fn (Builder $orders) => $orders
                        ->whereLike('order_number', $like($value), caseSensitive: false))),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('id', 'created_at', 'status', 'cost', 'shipped_at', 'delivered_at')
                ->rows(fn (Shipment $shipment) => $this->presenter->row($shipment)),
            'statuses' => ShipmentStatus::options(),
            'couriers' => $this->couriers->options(),
        ]);
    }

    public function store(ShipmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        /** @var Order $order */
        $order = $this->orders->findOrFail((int) $data['order_id']);

        $this->service->create($order, $data, $request->user()->id);

        return back()->with('success', __('Shipment created'));
    }

    public function status(ShipmentStatusRequest $request, int $shipment): RedirectResponse
    {
        /** @var Shipment $model */
        $model = $this->shipments->findOrFail($shipment);
        $data = $request->validated();

        $this->service->move($model, ShipmentStatus::from($data['status']), $data['note'] ?? null, $request->user()->id);

        return back()->with('success', __('Shipment updated'));
    }
}
