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
use Modules\Items\Entities\Product;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;
use Modules\Items\Http\Requests\Admin\OrderRequest;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Services\OrderService;
use Modules\Items\Support\IntegerFilter;
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
                            ->orWhereHas('user', fn (Builder $users) => $users->whereLike('name', $term)));
                    }),
                    AdminFilter::text('order_number'),
                    AllowedFilter::callback('customer', fn (Builder $query, mixed $value) => $query->whereHas('user', fn (Builder $users) => $users->whereLike('name', self::likeTerm($value)))),
                    IntegerFilter::make('status'),
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
                    'customer' => $order->user?->name,
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
            'submitToken' => (string) Str::uuid(),
        ]);
    }

    public function store(OrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $created = SubmitOnce::run('orders', $data['submit_token'], fn () => $this->orderService->create($data, $request->user()->id));

        return to_route('orders.index')->with('success', $created ? __('Order added successfully') : __('This form was already saved'));
    }

    public function edit(int $order): Response
    {
        $model = $this->orders->findForForm($order);

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
                'latitude' => $model->latitude,
                'longitude' => $model->longitude,
                'items' => $model->items->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'addons_total' => $item->addonOptions->sum(fn (AddonOption $option) => $option->pivot->price * $option->pivot->quantity),
                ])->values(),
            ],
            'selectedCustomer' => $model->user ? ['value' => $model->user->id, 'label' => $model->user->name] : null,
            'selectedProducts' => $model->items->map(fn (OrderItem $item) => $item->product)->filter()->unique('id')
                ->map(fn (Product $product) => $this->productOption($product))->values(),
        ]);
    }

    public function update(OrderRequest $request, int $order): RedirectResponse
    {
        $this->orderService->update($order, $request->validated());

        return to_route('orders.index')->with('success', __('Order updated successfully'));
    }

    public function destroy(int $order): RedirectResponse
    {
        /** @var Order $model */
        $model = $this->orders->findOrFail($order);

        $this->orderService->delete($model);

        return back()->with('success', __('Order deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->orderService->deleteMany($ids);

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
            'products' => $this->products->searchParents($term)
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
