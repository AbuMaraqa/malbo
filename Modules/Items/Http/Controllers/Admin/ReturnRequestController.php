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
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Entities\ReturnRequestHistory;
use Modules\Items\Entities\ReturnRequestLine;
use Modules\Items\Enums\ReturnReason;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Enums\ReturnRequestType;
use Modules\Items\Http\Requests\Admin\ReturnRequestRequest;
use Modules\Items\Http\Requests\Admin\ReturnRequestStatusRequest;
use Modules\Items\Http\Requests\Admin\ReturnRequestUpdateRequest;
use Modules\Items\Http\Requests\Admin\ReturnSettingsRequest;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Repositories\Contracts\ReturnRequestRepositoryInterface;
use Modules\Items\Services\ReturnRequestService;
use Modules\Store\Settings\StoreReturnSettings;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Return and exchange requests in the admin: the list, a request made by staff for a delivered
 * order (found by its number), and the detail page that approves, rejects, receives and completes
 * it. Every change goes through ReturnRequestService.
 */
class ReturnRequestController extends Controller
{
    public function __construct(
        private readonly ReturnRequestRepositoryInterface $requests,
        private readonly ReturnRequestService $service,
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Items/ReturnRequest/Index', [
            'table' => AdminTable::for($this->requests->tableQuery())
                ->filters(
                    AdminFilter::exact('status'),
                    AdminFilter::exact('type'),
                    AdminFilter::boolean('refunded'),
                    // Still being handled (requested, approved, received): the dashboard links here.
                    AllowedFilter::callback('open', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $query->whereIn('status', array_map(fn (ReturnRequestStatus $status) => $status->value, ReturnRequestStatus::open()))
                        : $query->whereNotIn('status', array_map(fn (ReturnRequestStatus $status) => $status->value, ReturnRequestStatus::open()))),
                    AllowedFilter::callback('order_number', fn (Builder $query, mixed $value) => $query->whereHas('order', fn (Builder $orders) => $orders
                        ->whereLike('order_number', '%'.addcslashes(trim((string) $value), '%_\\').'%', caseSensitive: false))),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('id', 'created_at', 'status', 'refund_amount')
                ->rows(fn (ReturnRequest $request) => [
                    'id' => $request->id,
                    'order_number' => $request->order?->order_number,
                    'customer' => $request->user?->name ?? $request->order?->user?->name ?? $request->order?->customer_name,
                    'type' => $request->type->value,
                    'type_label' => $request->type->getLabel(),
                    'status' => $request->status->value,
                    'status_label' => $request->status->getLabel(),
                    'status_tone' => $request->status->tone(),
                    'reason' => $request->reason->getLabel(),
                    'units' => (int) $request->lines->sum('quantity'),
                    'refund_amount' => $request->refund_amount,
                    'refunded' => $request->refunded,
                    'created_at' => $request->created_at?->format('Y-m-d H:i'),
                ]),
            'statuses' => ReturnRequestStatus::options(),
            'types' => ReturnRequestType::options(),
            'windowDays' => app(StoreReturnSettings::class)->return_window_days,
        ]);
    }

    public function add(Request $request): Response
    {
        $number = is_string($request->query('order')) ? mb_substr(trim($request->query('order')), 0, 40) : '';
        $order = $number !== '' ? $this->orders->findByNumber($number) : null;

        return Inertia::render('Items/ReturnRequest/Add', [
            'orderNumber' => $number,
            'notFound' => $number !== '' && $order === null,
            'order' => $order ? $this->orderForForm($order) : null,
            'blocked' => $order ? $this->service->eligibility($order) : null,
            'types' => ReturnRequestType::options(),
            'reasons' => ReturnReason::options(),
        ]);
    }

    public function store(ReturnRequestRequest $request): RedirectResponse
    {
        $data = $request->validated();
        /** @var Order $order */
        $order = $this->orders->findOrFail((int) $data['order_id']);

        $created = $this->service->create($order, $data, null, $request->user()->id);

        return to_route('return-requests.edit', $created->id)->with('success', __('Return request saved'));
    }

    public function edit(int $returnRequest): Response
    {
        $model = $this->requests->findForForm($returnRequest);

        return Inertia::render('Items/ReturnRequest/Edit', [
            'request' => [
                'id' => $model->id,
                'order' => [
                    'id' => $model->order?->id,
                    'number' => $model->order?->order_number,
                    'status' => $model->order?->status?->getLabel(),
                    'customer' => $model->user?->name ?? $model->order?->user?->name ?? $model->order?->customer_name,
                    'phone' => $model->order?->contact_phone,
                    'sub_total' => $model->order?->sub_total,
                    'discount' => $model->order?->discount,
                    'total' => $model->order?->total,
                    'delivery_fee' => $model->order?->delivery_fee,
                ],
                'type' => ['value' => $model->type->value, 'label' => $model->type->getLabel()],
                'status' => ['value' => $model->status->value, 'label' => $model->status->getLabel(), 'tone' => $model->status->tone()],
                'reason' => $model->reason->getLabel(),
                'customer_note' => $model->customer_note,
                'staff_note' => $model->staff_note,
                'refund_amount' => $model->refund_amount,
                'refunded' => $model->refunded,
                'refunded_at' => $model->refunded_at?->format('Y-m-d H:i'),
                'created_at' => $model->created_at?->format('Y-m-d H:i'),
                'created_by' => $model->createdBy?->name,
                // The refund's own math: the unit as sold (addons included), its share of the order
                // discount, and what the line gives back.
                'lines' => $model->lines->map(fn (ReturnRequestLine $line) => [
                    'id' => $line->id,
                    'name' => $line->orderItem ? self::itemName($line->orderItem) : __('Deleted product'),
                    'sku' => $line->orderItem?->sku,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->orderItem ? self::money(ReturnRequestService::unitPrice($line->orderItem)) : null,
                    'unit_discount' => $line->orderItem && $model->order ? self::money(ReturnRequestService::unitDiscount($model->order, $line->orderItem)) : null,
                    'refund' => $line->orderItem && $model->order ? self::money(ReturnRequestService::unitRefund($model->order, $line->orderItem) * $line->quantity) : null,
                    'exchange_for' => $line->exchangeProduct ? ReturnRequestService::productLabel($line->exchangeProduct) : null,
                ])->values(),
                'history' => $model->history->map(fn (ReturnRequestHistory $row) => [
                    'id' => $row->id,
                    'status' => $row->status->getLabel(),
                    'tone' => $row->status->tone(),
                    'note' => $row->note,
                    'at' => $row->created_at?->format('Y-m-d H:i'),
                    'by' => $row->createdBy?->name,
                ])->values(),
            ],
            'transitions' => array_values(array_map(
                fn (ReturnRequestStatus $status) => ['value' => $status->value, 'label' => $status->getLabel()],
                // Cancelling is the buyer's own move, from their account.
                array_filter(ReturnRequestStatus::cases(), fn (ReturnRequestStatus $status) => $status !== ReturnRequestStatus::CANCELLED && $model->status->canTransitionTo($status)),
            )),
        ]);
    }

    public function update(ReturnRequestUpdateRequest $request, int $returnRequest): RedirectResponse
    {
        /** @var ReturnRequest $model */
        $model = $this->requests->findOrFail($returnRequest);

        $this->service->update($model, $request->validated(), $request->user()->id);

        return back()->with('success', __('Return request saved'));
    }

    public function status(ReturnRequestStatusRequest $request, int $returnRequest): RedirectResponse
    {
        /** @var ReturnRequest $model */
        $model = $this->requests->findOrFail($returnRequest);
        $data = $request->validated();
        $note = $data['note'] ?? null;
        $staffId = $request->user()->id;

        match (ReturnRequestStatus::from($data['status'])) {
            ReturnRequestStatus::APPROVED => $this->service->approve($model, $note, $staffId),
            ReturnRequestStatus::REJECTED => $this->service->reject($model, $note, $staffId),
            ReturnRequestStatus::RECEIVED => $this->service->receive($model, $note, $staffId),
            ReturnRequestStatus::COMPLETED => $this->service->complete($model, $staffId, $note),
            ReturnRequestStatus::REQUESTED => abort(422),
        };

        return back()->with('success', __('Return request updated'));
    }

    public function settings(ReturnSettingsRequest $request): RedirectResponse
    {
        $this->service->saveWindow((int) $request->validated('return_window_days'));

        return back()->with('success', __('Settings saved'));
    }

    /**
     * The products staff may give in an exchange: any sellable product, 50 at a time.
     */
    public function products(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        return response()->json($this->products->searchSellable($term)
            ->map(fn (Product $product) => ['value' => $product->id, 'label' => (string) $product->name])
            ->values());
    }

    /**
     * @return array<string, mixed>
     */
    private function orderForForm(Order $order): array
    {
        $remaining = $this->service->remaining($order);

        return [
            'id' => $order->id,
            'number' => $order->order_number,
            'status' => $order->status->getLabel(),
            'customer' => $order->customer_name,
            'lines' => $order->items->map(fn (OrderItem $item) => [
                'order_item_id' => $item->id,
                'name' => self::itemName($item),
                'sku' => $item->sku,
                'quantity' => (int) $item->quantity,
                'max' => $remaining[$item->id] ?? 0,
                'exchange_options' => $this->service->exchangeOptions($item),
            ])->values(),
        ];
    }

    private static function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    /**
     * The line's name as it was sold, in the current language, with its options.
     */
    private static function itemName(OrderItem $item): string
    {
        $locale = app()->getLocale();
        $text = fn (?array $values) => (string) ($values[$locale] ?? collect($values ?? [])->first(fn ($value) => filled($value)) ?? '');
        $options = collect($item->options ?? [])->map(fn (array $option) => $text($option['value'] ?? null))->filter()->implode(' · ');
        $name = $text($item->product_name) ?: __('Deleted product');

        return $options !== '' ? $name.' — '.$options : $name;
    }
}
