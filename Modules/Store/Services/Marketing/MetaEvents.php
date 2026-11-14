<?php

namespace Modules\Store\Services\Marketing;

use App\Http\Middleware\HandleInertiaRequests;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Support\OrderBundles;
use Modules\Store\Services\PricingService;
use Modules\Store\Settings\MarketingSettings;
use Modules\Store\Support\CatalogIds;
use Modules\Store\Support\MarketingConsent;

/**
 * Meta events of the storefront, built once on the server: each gets its `event_id` here, goes to
 * the page for the browser Pixel (`marketing` prop) and, with the same id, to the Conversions API
 * after the response. Meta keeps one of the two.
 *
 * Nothing is built unless the request is a storefront page (never the admin or the wholesale
 * portal) and the visitor accepted marketing cookies. Values are storefront prices
 * (PricingService) or the order's own totals: never a wholesale or cost price.
 *
 * A page reached by a redirect right after a form post (the session carries that post's flash) is
 * not a new visit: its PageView and page events are not built again. The post's own events
 * (AddToCart) travel to that page in the flash.
 */
class MetaEvents
{
    public const CURRENCY = 'ILS';

    private const FLASH = 'store.meta_events';

    /** Pages that build no event at all: their address carries a signed, personal token. */
    private const SILENT_ROUTES = ['store.unsubscribe', 'store.unsubscribe.confirm', 'store.cart.restore'];

    /**
     * This request's server events, posted together in one call once the response is out.
     *
     * @var list<array<string, mixed>>
     */
    private array $pending = [];

    private bool $flushQueued = false;

    public function __construct(
        private readonly MetaConversionsApi $api,
        private readonly ProductRepositoryInterface $products,
        private readonly PricingService $pricing,
    ) {}

    /**
     * The `marketing` prop of a storefront page: null when nothing is set up or this is not a
     * storefront page; else the consent, and with consent the Pixel id and this visit's events.
     *
     * @param  (Closure(): list<array<string, mixed>|null>)|null  $pageEvents  built only for a new visit
     * @return array{consent: ?string, pixel_id: ?string, events: list<array<string, mixed>>}|null
     */
    public function props(Request $request, ?Closure $pageEvents = null): ?array
    {
        $settings = self::settings();

        if ((! $settings->pixelReady() && ! $settings->capiReady()) || ! self::storefront($request)) {
            return null;
        }

        $consent = MarketingConsent::choice($request);

        if ($consent !== MarketingConsent::GRANTED || self::silent($request)) {
            return ['consent' => $consent, 'pixel_id' => null, 'events' => []];
        }

        $events = (array) $request->session()->get(self::FLASH, []);

        if (! self::landing($request)) {
            $events = [...$events, $this->record($request, 'PageView'), ...($pageEvents ? $pageEvents() : [])];
        }

        return [
            'consent' => $consent,
            'pixel_id' => $settings->pixelReady() ? (string) $settings->meta_pixel_id : null,
            'events' => $settings->pixelReady() ? array_values(array_filter($events)) : [],
        ];
    }

    /**
     * Whether events are built for this request.
     */
    public function active(Request $request): bool
    {
        $settings = self::settings();

        return ($settings->pixelReady() || $settings->capiReady()) && self::storefront($request) && ! self::silent($request) && MarketingConsent::granted($request);
    }

    /**
     * ViewContent of a product page: the chosen (or default) variant's SKU and price, else the
     * product's.
     *
     * @param  array<string, mixed>  $quote  ProductPage::quote()
     */
    public function viewContent(Request $request, Product $product, array $quote): ?array
    {
        if (! $this->active($request)) {
            return null;
        }

        $variant = $quote['variant_id'] ? $product->variations->firstWhere('id', $quote['variant_id']) : null;

        return $this->record($request, 'ViewContent', [
            'currency' => self::CURRENCY,
            'value' => (float) $quote['price']['amount'],
            'content_ids' => [CatalogIds::of($variant ?? $product)],
            'content_type' => 'product',
            'content_name' => (string) $product->name,
        ], contact: self::account($request));
    }

    /**
     * AddToCart of a line just added: sent now, and flashed for the page the buyer lands on.
     */
    public function addToCart(Request $request, int $productId, int $quantity): void
    {
        if (! $this->active($request)) {
            return;
        }

        $product = $this->products->forCart([$productId])->get($productId);

        if ($product === null) {
            return;
        }

        $price = $this->pricing->for($product)->amount;
        $id = CatalogIds::of($product);

        $event = $this->record($request, 'AddToCart', [
            'currency' => self::CURRENCY,
            'value' => round($price * $quantity, 2),
            'content_ids' => [$id],
            'content_type' => 'product',
            'contents' => [['id' => $id, 'quantity' => $quantity, 'item_price' => $price]],
        ], sourceUrl: strtok(url()->previous(), '?#') ?: null, contact: self::account($request));

        $request->session()->flash(self::FLASH, array_values(array_filter([$event])));
    }

    /**
     * InitiateCheckout: the cart's lines and subtotal as checkout shows them.
     *
     * @param  list<array<string, mixed>>  $lines  CartService::lines()
     * @param  array{email?: ?string, phone?: ?string}  $contact  a contact the buyer already gave
     */
    public function initiateCheckout(Request $request, array $lines, array $contact = []): ?array
    {
        if (! $this->active($request)) {
            return null;
        }

        $contents = array_map(fn (array $line) => [
            'id' => CatalogIds::from($line['sku'], (int) $line['product_id']),
            'quantity' => (int) $line['quantity'],
            'item_price' => (float) $line['unit_price'],
        ], array_values(array_filter($lines, fn (array $line) => $line['available'])));

        return $this->record($request, 'InitiateCheckout', [
            'currency' => self::CURRENCY,
            'value' => round(array_sum(array_map(fn (array $item) => $item['item_price'] * $item['quantity'], $contents)), 2),
            'content_ids' => array_values(array_unique(array_column($contents, 'id'))),
            'content_type' => 'product',
            'contents' => $contents,
            'num_items' => array_sum(array_column($contents, 'quantity')),
        ], contact: [...self::account($request), ...array_filter($contact)]);
    }

    /**
     * Purchase on the confirmation page. Its id is the order's, so a reload is the same event; the
     * server sends it once.
     */
    public function purchase(Request $request, Order $order): ?array
    {
        if (! $this->active($request)) {
            return null;
        }

        $order->loadMissing('items');

        // A set's pieces are one item for Meta: the set, as the feed lists it.
        $contents = $order->items->groupBy(fn (OrderItem $item) => $item->bundle_group ?? 'line.'.$item->id)
            ->map(function ($lines) {
                /** @var OrderItem $item */
                $item = $lines->first();

                return $item->bundle_group === null ? [
                    'id' => CatalogIds::from($item->sku, (int) $item->product_id),
                    'quantity' => (int) $item->quantity,
                    'item_price' => (float) $item->price,
                ] : [
                    'id' => CatalogIds::from($item->bundle['sku'] ?? null, (int) $item->bundle_product_id),
                    'quantity' => OrderBundles::quantity($item),
                    'item_price' => OrderBundles::unitPrice($item),
                ];
            })->values()->all();

        return $this->record($request, 'Purchase', [
            'currency' => self::CURRENCY,
            'value' => (float) $order->total,
            'content_ids' => array_values(array_unique(array_column($contents, 'id'))),
            'content_type' => 'product',
            'contents' => $contents,
            'num_items' => array_sum(array_column($contents, 'quantity')),
        ], id: 'order.'.$order->order_number, contact: [
            'email' => $order->customer_email,
            'phone' => $order->contact_phone,
            'user_id' => $order->user_id ? (int) $order->user_id : null,
        ], sendToServer: Cache::add('store.meta.purchase.'.$order->id, true, now()->addDays(7)));
    }

    /**
     * Builds an event: queues its server copy (after the response) and returns what the page's
     * Pixel fires, or null when this request builds no events.
     *
     * @param  array<string, mixed>  $data  custom_data
     * @param  array{email?: ?string, phone?: ?string, user_id?: ?int}  $contact
     * @return array{name: string, id: string, data: array<string, mixed>|object}|null
     */
    public function record(Request $request, string $name, array $data = [], ?string $id = null, ?string $sourceUrl = null, array $contact = [], bool $sendToServer = true): ?array
    {
        if (! $this->active($request)) {
            return null;
        }

        $id ??= (string) Str::uuid();

        if ($sendToServer && self::settings()->capiReady()) {
            $event = array_filter([
                'event_name' => $name,
                'event_time' => time(),
                'event_id' => $id,
                // Without its query: a link may carry a token or a personal value.
                'event_source_url' => $sourceUrl ?? $request->url(),
                'action_source' => 'website',
                'user_data' => MetaUserData::for($request, $contact['email'] ?? null, $contact['phone'] ?? null, $contact['user_id'] ?? null),
                'custom_data' => $data === [] ? null : $data,
            ], fn ($value) => $value !== null);

            $this->pending[] = $event;

            if (! $this->flushQueued) {
                $this->flushQueued = true;
                defer(fn () => $this->flush(), always: true);
            }
        }

        return ['name' => $name, 'id' => $id, 'data' => $data === [] ? (object) [] : $data];
    }

    /**
     * Posts this request's server events in one call (after the response).
     */
    public function flush(): void
    {
        [$events, $this->pending, $this->flushQueued] = [$this->pending, [], false];

        if ($events !== []) {
            $this->api->send($events);
        }
    }

    private static function silent(Request $request): bool
    {
        return $request->routeIs(...self::SILENT_ROUTES);
    }

    /**
     * @return array{email?: ?string, phone?: ?string, user_id?: ?int}
     */
    private static function account(Request $request): array
    {
        $user = $request->user();

        return $user ? ['email' => $user->email, 'phone' => $user->phone, 'user_id' => (int) $user->id] : [];
    }

    private static function storefront(Request $request): bool
    {
        return HandleInertiaRequests::section($request) === 'storefront';
    }

    /**
     * The page right after a form post: the session still carries what that post flashed.
     */
    private static function landing(Request $request): bool
    {
        return $request->hasSession() && (array) $request->session()->get('_flash.old', []) !== [];
    }

    private static function settings(): MarketingSettings
    {
        return app(MarketingSettings::class);
    }
}
