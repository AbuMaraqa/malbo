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
use Modules\Core\Entities\User;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Repositories\Contracts\CityRepositoryInterface;
use Modules\GeoLocation\Repositories\Contracts\DistrictRepositoryInterface;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\OrderStatusHistory;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;
use Modules\Items\Http\Requests\Admin\OrderRequest;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Repositories\Contracts\ShipmentRepositoryInterface;
use Modules\Items\Services\OrderService;
use Modules\Items\Support\ShipmentPresenter;
use Modules\Items\Support\IntegerFilter;
use Modules\Items\Support\OrderBundles;
use Modules\Items\Support\RecordInfo;
use Modules\Items\Support\SubmitOnce;
use Spatie\QueryBuilder\AllowedFilter;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderService $orderService,
        private readonly ProductRepositoryInterface $products,
        private readonly UserRepositoryInterface $users,
        private readonly BranchRepositoryInterface $branches,
        private readonly CityRepositoryInterface $cities,
        private readonly DistrictRepositoryInterface $districts,
        private readonly ShipmentRepositoryInterface $shipments,
        private readonly ShipmentPresenter $shipmentPresenter,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Items/Order/Index', [
            'table' => AdminTable::for($this->orders->tableQuery())
                ->filters(
                    AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                        $term = self::likeTerm($value);

                        $query->where(fn (Builder $query) => $query
                            ->whereLike('order_number', $term)
                            ->orWhereLike('contact_phone', $term)
                            ->orWhereLike('customer_name', $term)
                            ->orWhereHas('user', fn (Builder $users) => $users->whereLike('name', $term)));
                    }),
                    AdminFilter::text('order_number'),
                    AllowedFilter::callback('customer', fn (Builder $query, mixed $value) => $query->whereHas('user', fn (Builder $users) => $users->whereLike('name', self::likeTerm($value)))),
                    IntegerFilter::make('status'),
                    // Cash orders from the store wait here until someone calls the buyer.
                    AllowedFilter::callback('awaiting_confirmation', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $query->where('status', OrderStatus::AWAITING_CONFIRMATION->value)
                        : $query->where('status', '!=', OrderStatus::AWAITING_CONFIRMATION->value)),
                    AdminFilter::exact('channel'),
                    IntegerFilter::make('payment_status'),
                    IntegerFilter::make('payment_method'),
                    IntegerFilter::make('delivery_type'),
                    IntegerFilter::make('branch_id'),
                    ...AdminFilter::numberRange('total'),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('id', 'order_number', 'sub_total', 'discount', 'delivery_fee', 'total', 'status', 'payment_status', 'created_at')
                ->rows(fn (Order $order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    // A wholesale order is the shop's: its name, as saved on the order.
                    'customer' => $order->channel === OrderChannel::B2B && filled($order->customer_name) ? $order->customer_name : ($order->user?->name ?? $order->customer_name),
                    'customer_name' => $order->customer_name,
                    'channel' => $order->channel->value,
                    'channel_label' => $order->channel->getLabel(),
                    'branch' => $order->branch?->name,
                    'sub_total' => $order->sub_total,
                    'discount' => $order->discount,
                    'delivery_fee' => $order->delivery_fee,
                    'total' => $order->total,
                    'status' => $order->status?->getLabel(),
                    'status_tone' => self::tone($order->status?->getColor()),
                    'payment_status' => $order->payment_status?->getLabel(),
                    'payment_status_tone' => self::tone($order->payment_status?->getColor()),
                    'payment_method' => $order->payment_method?->getLabel(),
                    'delivery_address' => $order->delivery_address,
                    'contact_phone' => $order->contact_phone,
                    'notes' => $order->notes,
                    'created_at' => $order->created_at?->format('Y-m-d H:i'),
                    'items' => $order->items->map(fn (OrderItem $item) => [
                        'name' => $item->product?->name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'total_price' => $item->total_price,
                    ])->values(),
                ]),
            'statuses' => self::options(OrderStatus::cases()),
            'channels' => array_map(fn (OrderChannel $channel) => ['value' => $channel->value, 'label' => $channel->getLabel()], OrderChannel::cases()),
            'paymentStatuses' => self::options(PaymentStatus::cases()),
            'paymentMethods' => self::options(PaymentMethod::cases()),
            'deliveryTypes' => self::options(DeliveryType::cases()),
            'branches' => fn () => $this->branchOptions(),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Items/Order/Add', [
            ...$this->formProps(),
            'statuses' => self::options(OrderService::reachableFrom(OrderStatus::PENDING)),
            'required' => OrderRequest::requiredFor(null),
            'submitToken' => (string) Str::uuid(),
        ]);
    }

    public function store(OrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $created = SubmitOnce::run('orders', $data['submit_token'], fn () => $this->orderService->create($data, $request->user()->id));

        return to_route('orders.index')->with('success', $created ? __('Order added successfully') : __('This form was already saved'));
    }

    public function edit(Request $request, int $order): Response
    {
        $model = $this->orders->findForForm($order);
        // The shipment card, for those who may see shipments.
        $shipment = $request->user()->can('Shipment View List')
            ? $this->shipmentPresenter->card($this->shipments->forOrderPage($model->id), $model)
            : null;

        return Inertia::render('Items/Order/Edit', [
            ...$this->formProps(),
            'statuses' => self::options(OrderService::reachableFrom($model->status)),
            'order' => [
                'id' => $model->id,
                'order_number' => $model->order_number,
                'user_id' => $model->user_id,
                'branch_id' => $model->branch_id,
                'city_id' => $model->city_id,
                'district_id' => $model->district_id,
                'sub_total' => $model->sub_total,
                'discount' => $model->discount,
                'delivery_fee' => $model->delivery_fee,
                'total' => $model->total,
                'status' => $model->status?->value,
                'payment_method' => $model->payment_method?->value,
                'payment_status' => $model->payment_status?->value,
                'delivery_type' => $model->delivery_type?->value,
                'delivery_address' => $model->delivery_address,
                'contact_phone' => $model->contact_phone,
                'notes' => $model->notes,
                'internal_notes' => $model->internal_notes,
                'customer_name' => $model->customer_name,
                'customer_email' => $model->customer_email,
                'channel' => $model->channel->value,
                'coupon_code' => $model->coupon_code,
                'latitude' => $model->latitude,
                'longitude' => $model->longitude,
                'items' => $model->items->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'addons_total' => $item->addonOptions->sum(fn (AddonOption $option) => $option->pivot->price * $option->pivot->quantity),
                    // As sold: a set's pieces are shown under their set, and kept or removed with it.
                    'name' => self::lineName($item),
                    'total' => $item->total_price,
                    'bundle' => $item->bundle_group === null ? null : [
                        'key' => $item->bundle_group,
                        'name' => OrderBundles::name($item, app()->getLocale()),
                        'quantity' => OrderBundles::quantity($item),
                        'unit_price' => OrderBundles::unitPrice($item),
                    ],
                ])->values(),
            ],
            'required' => OrderRequest::requiredFor($model),
            'canConfirm' => $model->status === OrderStatus::AWAITING_CONFIRMATION,
            // A delivered order can be returned or exchanged (Return requests → Add).
            'canRequestReturn' => $model->status === OrderStatus::DELIVERED,
            // Delivered (or later) or with a return request: the lines are shown, not edited.
            'linesLocked' => $this->orderService->linesLocked($model),
            'couponNoLongerApplies' => ! $this->orderService->couponStillApplies($model),
            'history' => $model->statusHistory->map(fn (OrderStatusHistory $row) => [
                'id' => $row->id,
                'status' => $row->status->getLabel(),
                'tone' => self::tone($row->status->getColor()),
                'at' => $row->created_at?->format('Y-m-d H:i'),
                'by' => $row->createdBy?->name,
            ])->values(),
            'shipment' => $shipment,
            'selectedCustomer' => $model->user ? ['value' => $model->user->id, 'label' => $model->user->name] : null,
            'selectedProducts' => $model->items->map(fn (OrderItem $item) => $item->product)->filter()->unique('id')
                ->map(fn (Product $product) => $this->productOption($product))->values(),
        ]);
    }

    public function update(OrderRequest $request, int $order): RedirectResponse
    {
        $this->orderService->update($order, $request->validated(), $request->user()->id);

        return to_route('orders.index')->with('success', __('Order updated successfully'));
    }

    /**
     * One click for a cash order awaiting confirmation: MALBO called the buyer and it goes ahead.
     */
    public function confirm(Request $request, int $order): RedirectResponse
    {
        /** @var Order $model */
        $model = $this->orders->findOrFail($order);

        $this->orderService->changeStatus($model, OrderStatus::CONFIRMED, $request->user()->id);

        return back()->with('success', __('Order confirmed'));
    }

    public function destroy(Request $request, int $order): RedirectResponse
    {
        /** @var Order $model */
        $model = $this->orders->findOrFail($order);

        $this->orderService->delete($model, $request->user()?->id);

        return back()->with('success', __('Order deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->orderService->deleteMany($ids, $request->user()?->id);

        return back()->with('success', __('Orders deleted successfully'));
    }

    public function info(int $order): JsonResponse
    {
        return response()->json(RecordInfo::of($this->orders->findOrFail($order)));
    }

    /**
     * The searchable customer and product lists of the form, 50 at a time.
     */
    public function lookup(Request $request, string $type): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        return response()->json(match ($type) {
            'customers' => $this->users->query()
                ->when($term !== '', fn (Builder $query) => $query->whereLike('name', self::likeTerm($term)))
                ->orderBy('name')->limit(50)->get()
                ->map(fn (User $user) => ['value' => $user->id, 'label' => $user->name])->values(),
            'products' => $this->products->searchSellable($term)
                ->map(fn (Product $product) => $this->productOption($product))->values(),
        });
    }

    /**
     * The lists both forms choose from.
     *
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'paymentMethods' => self::options(PaymentMethod::cases()),
            'paymentStatuses' => self::options(PaymentStatus::cases()),
            'deliveryTypes' => self::options(DeliveryType::cases()),
            'branches' => $this->branchOptions(),
            'cities' => collect($this->cities->options())->map(fn (array $city) => ['value' => $city['value'], 'label' => $city['label']])->values(),
            'districts' => $this->districts->query()->with('translations')->orderBy('id')->get()
                ->map(fn (District $district) => ['value' => $district->id, 'label' => (string) $district->name, 'city_id' => $district->city_id])->values(),
        ];
    }

    /**
     * A line's product as it was sold, with its options ("Shirt — M / Black").
     */
    private static function lineName(OrderItem $item): string
    {
        $locale = app()->getLocale();
        $text = fn (?array $values) => (string) ($values[$locale] ?? collect($values ?? [])->first(fn ($value) => filled($value)) ?? '');
        $values = collect($item->options ?? [])->map(fn (array $option) => $text($option['value'] ?? null))->filter()->implode(' / ');
        $name = $text($item->product_name) ?: __('Deleted product');

        return $values !== '' ? $name.' — '.$values : $name;
    }

    /**
     * @return array{value: int, label: string, price: float}
     */
    private function productOption(Product $product): array
    {
        return ['value' => $product->id, 'label' => (string) $product->name, 'price' => $this->orderService->currentPrice($product)];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{value: int, label: string}>
     */
    private function branchOptions(): \Illuminate\Support\Collection
    {
        return $this->branches->query()->with('translations')->orderBy('id')->get()
            ->map(fn ($branch) => ['value' => $branch->id, 'label' => (string) $branch->name])->values();
    }

    /**
     * @param  array<int, OrderStatus|PaymentStatus|PaymentMethod|DeliveryType>  $cases
     * @return list<array{value: int, label: string}>
     */
    private static function options(array $cases): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => (string) $case->getLabel()], $cases);
    }

    /**
     * The enums speak in palette names; the Badge component in tones.
     */
    private static function tone(?string $color): string
    {
        return match ($color) {
            'success' => 'success',
            'warning' => 'warning',
            'danger' => 'danger',
            'info' => 'info',
            'primary' => 'brand',
            default => 'neutral',
        };
    }

    private static function likeTerm(mixed $value): string
    {
        return '%'.addcslashes(trim((string) $value), '%_\\').'%';
    }
}
