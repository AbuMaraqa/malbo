<?php

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\CartReminder;
use Modules\Store\Repositories\Contracts\CartRepositoryInterface;
use Modules\Store\Services\AbandonedCartService;
use Modules\Store\Services\CartService;
use Modules\Store\Settings\MarketingSettings;
use Modules\Store\Support\InLocale;
use Modules\Store\Support\Price;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Abandoned carts: carts whose buyer agreed to reminders, quiet for the set time, with what they
 * are worth at today's prices, and the ones that became orders after a reminder (recovered).
 */
class AbandonedCartController extends Controller
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly AbandonedCartService $abandoned,
        private readonly CartService $cartService,
    ) {}

    public function index(): Response
    {
        $table = AdminTable::for($this->carts->abandonedQuery(app(MarketingSettings::class)->abandoned_after_minutes)->with('order'))
            ->search(['contact_email', 'contact_phone'], null)
            ->filters(
                AllowedFilter::callback('status', fn (Builder $query, mixed $value) => match ($value) {
                    'recovered' => $query->whereNotNull('order_id'),
                    'open' => $query->whereNull('order_id'),
                    default => $query,
                }),
                AllowedFilter::callback('reminded', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ? $query->whereHas('reminders', fn (Builder $sent) => $sent->where('status', CartReminder::SENT))
                    : $query->whereDoesntHave('reminders', fn (Builder $sent) => $sent->where('status', CartReminder::SENT))),
                AdminFilter::exact('locale'),
                ...AdminFilter::dateRange('last_activity_at'),
            )
            ->sorts('last_activity_at', 'items_count', 'reminders_sent', 'id')
            ->defaultSort('-last_activity_at')
            ->rows(fn (Cart $cart) => [
                'id' => $cart->id,
                'contact' => $cart->contact_email ?: $cart->user?->email ?: $cart->contact_phone ?: $cart->user?->phone,
                'customer' => $cart->user?->name,
                'items' => (int) $cart->pieces,
                'lines' => (int) $cart->items_count,
                'value' => null,
                'locale' => $cart->locale,
                'last_activity' => $cart->last_activity_at?->diffForHumans(),
                'last_activity_at' => $cart->last_activity_at?->translatedFormat('j F Y, H:i'),
                'reminders_sent' => (int) $cart->reminders_sent,
                'recovered' => $cart->order_id !== null && (int) $cart->reminders_sent > 0,
                'order_number' => $cart->order?->order_number,
            ]);

        // Values at today's prices, one read of the products for the whole page.
        $values = $this->abandoned->values($this->carts->query()->with('items')->whereKey(array_column($table['data'], 'id'))->get());
        $table['data'] = array_map(fn (array $row) => [...$row, 'value' => (new Price($values[$row['id']] ?? 0))->format()], $table['data']);

        return Inertia::render('Store/AbandonedCart/Index', ['table' => $table]);
    }

    public function show(int $cart): Response
    {
        $model = $this->carts->findForAdmin($cart);
        abort_if($model === null, 404);

        $contact = $this->abandoned->contact($model);
        $lines = $this->cartService->lines($model);

        return Inertia::render('Store/AbandonedCart/Show', [
            'cart' => [
                'id' => $model->id,
                'customer' => $model->user?->name,
                'email' => $contact['email'] ?? $model->contact_email ?? $model->user?->email,
                'phone' => $contact['phone'] ?? $model->contact_phone ?? $model->user?->phone,
                'consent_at' => $model->contact_consent_at?->translatedFormat('j F Y, H:i'),
                'locale' => InLocale::valid($model->locale),
                'value' => (new Price(CartService::subtotal($lines)))->format(),
                'created_at' => $model->created_at?->translatedFormat('j F Y, H:i'),
                'last_activity_at' => $model->last_activity_at?->translatedFormat('j F Y, H:i'),
                'recovered' => $this->abandoned->recovered($model),
                'order' => $model->order ? ['number' => $model->order->order_number, 'url' => route('orders.edit', ['order' => $model->order->id])] : null,
            ],
            'lines' => array_map(fn (array $line) => [
                'id' => $line['id'],
                'name' => $line['name'],
                'options' => $line['options'],
                'image' => $line['image'],
                'sku' => $line['sku'],
                'quantity' => $line['quantity'],
                'price' => $line['price']['label'],
                'total' => $line['total_label'],
                'available' => $line['available'],
                'problem' => $line['problem'],
            ], $lines),
            'reminders' => $model->reminders->map(fn (CartReminder $reminder) => [
                'sequence' => $reminder->sequence,
                'channel' => $reminder->channel,
                'recipient' => $reminder->recipient,
                'status' => $reminder->status,
                'sent_at' => ($reminder->sent_at ?? $reminder->created_at)?->translatedFormat('j F Y, H:i'),
            ])->values()->all(),
        ]);
    }
}
