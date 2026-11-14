<?php

namespace Modules\Store\Services;

use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Entities\ReturnRequestHistory;
use Modules\Items\Entities\ReturnRequestLine;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ReturnReason;
use Modules\Items\Enums\ReturnRequestType;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Repositories\Contracts\ReturnRequestRepositoryInterface;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Items\Services\OrderService;
use Modules\Items\Services\ReturnRequestService;
use Modules\Store\Support\Phone;
use Modules\Store\Support\Price;

/**
 * The orders page of a storefront account and an order's detail. Which orders an account sees is
 * decided in OrderRepository::forCustomer. The detail is what tracking shows (lines with their
 * names and options as sold, totals, the status timeline) plus the coupon and the account's
 * return requests: never an internal note, and never a staff note on a request.
 */
class AccountOrders
{
    public const PER_PAGE = 10;

    /** Past orders an account may try to claim in an hour. */
    public const CLAIMS_PER_HOUR = 5;

    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderTracking $tracking,
        private readonly ReturnRequestService $returns,
        private readonly ReturnRequestRepositoryInterface $returnRequests,
        private readonly OrderService $orderService,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, prev_url: ?string, next_url: ?string, total: int}
     */
    public function page(User $user): array
    {
        $page = $this->orders->forCustomer((int) $user->id, self::PER_PAGE);

        return [
            'data' => collect($page->items())->map(fn (Order $order) => [
                'number' => $order->order_number,
                'placed_at' => $order->created_at?->format('Y-m-d'),
                'status' => ['key' => strtolower($order->status->name), 'label' => $order->status->getLabel()],
                'items_count' => (int) $order->items_count,
                'total_label' => (new Price((float) $order->total))->format(),
                'url' => route('store.account.orders.show', ['number' => $order->order_number]),
            ])->values()->all(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'prev_url' => $page->previousPageUrl(),
            'next_url' => $page->nextPageUrl(),
            'total' => $page->total(),
        ];
    }

    public function find(User $user, string $number): ?Order
    {
        return $this->orders->findForCustomer((int) $user->id, $number);
    }

    /**
     * "Add a past order to my account": the order number and the phone it was placed with, checked
     * as tracking checks them (Phone::same). Only that one order, and only while it has no account.
     * Any mismatch (no such number, another phone, another account's order) gets the tracking
     * page's message. Limited by the tracking limiter of the address and CLAIMS_PER_HOUR per account.
     *
     * @throws ValidationException on `number`
     */
    public function claim(User $user, string $ip, string $number, string $phone): Order
    {
        $addressKey = 'store-track:'.$ip;
        $accountKey = 'store-claim:'.$user->id;

        if (RateLimiter::tooManyAttempts($addressKey, OrderTracking::ATTEMPTS) || RateLimiter::tooManyAttempts($accountKey, self::CLAIMS_PER_HOUR)) {
            throw ValidationException::withMessages(['number' => __('Too many attempts. Try again later.')]);
        }

        RateLimiter::hit($addressKey, 60);
        RateLimiter::hit($accountKey, 3600);

        $order = $this->orders->findForTracking(trim($number));
        $matches = $order !== null && Phone::same($order->contact_phone, $phone) && ($order->user_id === null || (int) $order->user_id === (int) $user->id);

        if (! $matches || ! $this->orderService->claimGuestOrder($order->id, (int) $user->id)) {
            throw ValidationException::withMessages(['number' => __('We could not find an order with this number and phone. Check them and try again.')]);
        }

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(User $user, Order $order): array
    {
        $blocked = $this->returns->eligibility($order, $user);

        return [
            ...$this->tracking->result($order),
            'coupon_code' => $order->coupon_code,
            'returns' => $this->returnRequests->forOrder($order->id)->map(fn (ReturnRequest $request) => $this->request($request))->values()->all(),
            // A delivered order offers the form, or says why it cannot.
            'return_form' => $blocked === null ? $this->form($order) : null,
            'return_blocked' => $blocked !== null && $order->status === OrderStatus::DELIVERED ? $blocked : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function form(Order $order): array
    {
        $remaining = $this->returns->remaining($order);

        return [
            'lines' => $order->items
                ->filter(fn (OrderItem $item) => ($remaining[$item->id] ?? 0) > 0)
                ->map(fn (OrderItem $item) => [
                    'order_item_id' => $item->id,
                    'name' => self::text($item->product_name) ?: __('Product'),
                    'options' => self::options($item),
                    'max' => $remaining[$item->id],
                    'exchange_options' => $this->returns->exchangeOptions($item),
                ])->values()->all(),
            'types' => ReturnRequestType::options(),
            'reasons' => ReturnReason::options(),
            'window_ends' => $this->returns->windowEnd($order)->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function request(ReturnRequest $request): array
    {
        return [
            'id' => $request->id,
            'type' => $request->type->getLabel(),
            'status' => ['key' => $request->status->value, 'label' => $request->status->getLabel()],
            'reason' => $request->reason->getLabel(),
            'customer_note' => $request->customer_note,
            'refund_label' => $request->type === ReturnRequestType::RETURN ? (new Price((float) $request->refund_amount))->format() : null,
            'created_at' => $request->created_at?->format('Y-m-d'),
            // Still waiting for staff: the buyer may withdraw it.
            'can_cancel' => $request->status === ReturnRequestStatus::REQUESTED,
            'lines' => $request->lines->map(fn (ReturnRequestLine $line) => [
                'name' => $line->orderItem ? (self::text($line->orderItem->product_name) ?: __('Product')) : __('Product'),
                'options' => $line->orderItem ? self::options($line->orderItem) : [],
                'quantity' => $line->quantity,
                'exchange_for' => $line->exchangeProduct ? ReturnRequestService::productLabel($line->exchangeProduct) : null,
            ])->values()->all(),
            // Statuses and dates only: what staff wrote stays with staff.
            'history' => $request->history->map(fn (ReturnRequestHistory $row) => [
                'status' => $row->status->getLabel(),
                'date' => $row->created_at?->format('Y-m-d H:i'),
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private static function options(OrderItem $item): array
    {
        return collect($item->options ?? [])->map(fn (array $option) => [
            'name' => self::text($option['name'] ?? null),
            'value' => self::text($option['value'] ?? null),
        ])->values()->all();
    }

    /**
     * A snapshot text (locale => text) in the current language, else in any.
     *
     * @param  array<string, string>|null  $values
     */
    private static function text(?array $values): string
    {
        $locale = app()->getLocale();

        return (string) ($values[$locale] ?? collect($values ?? [])->first(fn ($value) => filled($value)) ?? '');
    }
}
