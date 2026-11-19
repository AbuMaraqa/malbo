<?php

namespace Tests\Feature\E2E;

use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\Currency;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Entities\Governorate;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\Brand;
use Modules\Items\Entities\Label;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Entities\Tag;
use Modules\Items\Services\ReturnRequestService;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\InstagramPost;
use Modules\Store\Entities\Page;
use Modules\Store\Entities\Review;
use Modules\Store\Services\HeroSlideService;
use Modules\Store\Services\InstagramService;
use Modules\Store\Services\OptOutService;
use Modules\Wholesale\Entities\CatalogShare;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Services\MerchantLedger;
use Spatie\Activitylog\Models\Activity;

/**
 * Every named GET route, opened as the person it is for (staff for the admin and the app API, a
 * customer for the account, an approved merchant for the portal, a guest for the storefront), with
 * its parameters taken from the demo store, in each language: none answers with a server error, an
 * Inertia page always has its Vue file, and documents (PDF, CSV, XML) come with their content type.
 * Routes it cannot fill are printed, never skipped silently.
 */
class RouteCrawlTest extends E2ETestCase
{
    private const LOCALES = ['ar', 'en', 'he'];

    /** Not pages: the dev tools, files on disk and Laravel's own endpoints. */
    private const NOT_PAGES = ['_inertia/devtools/entries', '_inertia/devtools/entries/{id}', 'storage/{path}', 'sanctum/csrf-cookie', 'up'];

    /** Routes that answer with a document, and its content type. */
    private const DOCUMENTS = [
        'orders.invoice' => 'application/pdf',
        'orders.waybill' => 'application/pdf',
        'orders.waybills' => 'application/pdf',
        'products.barcodes.pdf' => 'application/pdf',
        'merchants.statement' => 'application/pdf',
        'merchants.receipt' => 'image/',
        'merchants.document' => 'application/pdf',
        'store.wholesale.statement.pdf' => 'application/pdf',
        'store.wholesale.catalog-pdf.download' => 'application/pdf',
        'store.wholesale.catalog-pdf.shared' => 'application/pdf',
        'store.feeds.meta-catalog' => 'application/xml',
        'store.feeds.meta-catalog-csv' => 'text/csv',
        'store.sitemap' => 'application/xml',
        'robots' => 'text/plain',
    ];

    /** @var array<string, mixed> parameter name => value */
    private array $values = [];

    /** @var array<string, array<string, mixed>> route name => its own parameter values */
    private array $byRoute = [];

    /** @var array<string, string> route name => a signed URL (with {locale} to fill) */
    private array $signed = [];

    private User $customer;

    private User $merchantUser;

    public function test_every_named_get_route(): void
    {
        $this->records();

        $visited = 0;
        $failures = [];
        $unfilled = [];
        $documents = [];

        foreach (Router::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            if (! in_array('GET', $route->methods(), true) || in_array($route->uri(), self::NOT_PAGES, true)) {
                continue;
            }

            $name = $route->getName();
            $label = $name ?: $route->uri();
            $locales = in_array('locale', $route->parameterNames(), true) ? self::LOCALES : [null];

            foreach ($locales as $locale) {
                $url = $this->urlFor($route, $locale);

                if ($url === null) {
                    $unfilled[$label] = $route->uri().' (missing: '.implode(', ', $this->missing($route)).')';

                    continue;
                }

                $response = $this->visit($route, $url);
                $visited++;
                $status = $response->getStatusCode();

                if ($status >= 500) {
                    $failures[] = "{$label} {$url} → {$status}: ".Str::limit((string) ($response->exception?->getMessage() ?? ''), 300);

                    continue;
                }

                if ($component = $this->pageComponent($response)) {
                    if (! is_file($this->vueFile($component))) {
                        $failures[] = "{$label} {$url} renders {$component}, which has no Vue file";
                    }
                }

                if ($name !== null && isset(self::DOCUMENTS[$name])) {
                    $type = (string) $response->headers->get('Content-Type');
                    $documents[$name] = "{$status} {$type}";

                    if ($status !== 200 || ! str_starts_with($type, self::DOCUMENTS[$name])) {
                        $failures[] = "{$label} {$url} → {$status} {$type}, expected 200 ".self::DOCUMENTS[$name];
                    }
                }
            }
        }

        fwrite(STDERR, "\nRoute crawl: {$visited} requests, ".count($documents)." document routes checked.\n");
        fwrite(STDERR, 'Routes not filled ('.count($unfilled)."):\n".($unfilled === [] ? "  none\n" : '  '.implode("\n  ", array_map(fn ($key, $why) => "{$key}: {$why}", array_keys($unfilled), $unfilled))."\n"));

        $this->assertSame([], $failures, "Routes that failed:\n".implode("\n", $failures));
        $this->assertSame([], $unfilled, 'Every named GET route is visited.');
        $this->assertGreaterThan(400, $visited);
    }

    /**
     * Whose page it is: staff, a customer, a merchant or anybody.
     */
    private function visit(Route $route, string $url): TestResponse
    {
        // Sanctum::actingAs() makes its guard the default: every other page is opened on the web guard.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        unset($this->defaultCookies['store_cart']);
        $middleware = implode(',', $route->gatherMiddleware());
        $uri = $route->uri();

        if (str_starts_with($uri, 'api/')) {
            Sanctum::actingAs($this->admin(), ['*']);

            return $this->getJson($url);
        }

        $guestOnly = str_contains($middleware, 'guest') || str_contains($middleware, 'RedirectIfAuthenticated');
        $signedIn = str_contains($middleware, 'auth') || str_contains($middleware, 'Authenticate');

        $user = match (true) {
            $guestOnly || ! $signedIn => null,
            str_starts_with($uri, '{locale}/account') => $this->customer,
            str_starts_with($uri, '{locale}/wholesale') => $this->merchantUser,
            default => $this->admin(),
        };

        return ($user ? $this->actingAs($user) : $this)->get($url);
    }

    private function urlFor(Route $route, ?string $locale): ?string
    {
        $name = (string) $route->getName();

        if (isset($this->signed[$name])) {
            return str_replace('{locale}', (string) $locale, $this->signed[$name]);
        }

        if ($this->missing($route) !== []) {
            return null;
        }

        $parameters = [];

        foreach ($route->parameterNames() as $parameter) {
            $parameters[$parameter] = $parameter === 'locale' ? $locale : ($this->byRoute[$this->key($route)][$parameter] ?? $this->values[$parameter]);
        }

        $path = preg_replace_callback('/\{(\w+)\??\}/', fn (array $match) => rawurlencode((string) $parameters[$match[1]]), $route->uri());

        return $this->siteUrl('/'.ltrim($path, '/').($this->queries()[$name] ?? ''));
    }

    /**
     * @return list<string> parameters with no value
     */
    private function missing(Route $route): array
    {
        $own = $this->byRoute[$this->key($route)] ?? [];

        return array_values(array_filter($route->parameterNames(), fn (string $parameter) => $parameter !== 'locale'
            && ! array_key_exists($parameter, $own) && ! array_key_exists($parameter, $this->values)));
    }

    /**
     * A route's name, or its URI when it has none (two collections redirects share "store.").
     */
    private function key(Route $route): string
    {
        $name = (string) $route->getName();

        return $name === '' || $name === 'store.' ? $route->uri() : $name;
    }

    /**
     * @return array<string, string> route name => query string a page needs
     */
    private function queries(): array
    {
        $order = $this->demo()->orders['delivered-old'];

        return [
            'orders.waybills' => '?ids[]='.$this->demo()->orders['with-courier']->id,
            'store.account.password.reset' => '?email='.urlencode($this->customer->email),
            'password.reset' => '?email='.urlencode($this->admin()->email),
            'store.track' => '?number='.$order->order_number,
            'products.look.lookup' => '?q=shirt',
            'bundles.lookup' => '?q=shirt',
            'campaign-pages.products' => '?q=shirt',
            'instagram-posts.products' => '?q=shirt',
            'return-requests.products' => '?q=shirt',
            'stock.products' => '?q=shirt',
            'store.search.suggest' => '?q='.rawurlencode('قميص'),
        ];
    }

    /**
     * The Inertia component a response renders, if any.
     */
    private function pageComponent(TestResponse $response): ?string
    {
        if ($response->headers->get('X-Inertia') === 'true') {
            return $response->json('component');
        }

        $original = $response->baseResponse->original ?? null;

        if ($original instanceof \Illuminate\View\View) {
            return $original->getData()['page']['component'] ?? null;
        }

        return null;
    }

    private function vueFile(string $component): string
    {
        [$first, $rest] = explode('/', $component, 2) + [1 => ''];
        $root = base_path();

        return is_dir("{$root}/Modules/{$first}")
            ? "{$root}/Modules/{$first}/resources/js/Pages/{$rest}.vue"
            : "{$root}/resources/js/Pages/{$component}.vue";
    }

    /**
     * Records for every kind of parameter, from the demo store and a few more.
     */
    private function records(): void
    {
        Storage::fake('local');
        Storage::fake(config('media-library.disk_name'));
        $demo = $this->demo();
        $admin = $this->admin();
        $this->customer = $demo->customers['rami'];
        /** @var Merchant $merchant */
        $merchant = $demo->extra['merchants']['approved'];
        $this->merchantUser = $merchant->user;

        $addon = Addon::factory()->create();
        $brand = Brand::factory()->create();
        $label = Label::factory()->create();
        $tag = Tag::factory()->create();
        $post = app(InstagramService::class)->create([
            'permalink' => 'https://www.instagram.com/p/CRAWL/', 'caption' => 'crawl', 'is_active' => true,
            'products' => [$demo->products['bomber']->id], 'image' => UploadedFile::fake()->image('post.jpg', 400, 400),
        ], $admin->id);
        $slide = app(HeroSlideService::class)->create([
            'title' => 'crawl', 'link' => '/ar/shop', 'is_active' => true, 'image' => UploadedFile::fake()->image('slide.jpg', 600, 300),
        ], 'ar', $admin->id);
        $return =app(ReturnRequestService::class)->create($demo->orders['delivered-recent'], [
            'type' => 'return', 'reason' => 'wrong_size', 'customer_note' => null,
            'lines' => [['order_item_id' => $demo->orders['delivered-recent']->items()->value('id'), 'quantity' => 1, 'exchange_product_id' => null]],
        ], $this->customer);
        $receipt = app(MerchantLedger::class)->recordPayment($merchant, [
            'amount' => 10, 'method' => 'cash', 'reference' => 'R-1', 'note' => null, 'entry_date' => now()->toDateString(),
        ], UploadedFile::fake()->image('receipt.jpg', 200, 200), $admin->id);
        Storage::disk('local')->put('wholesale/registers/crawl.pdf', '%PDF-1.4 crawl');
        $merchant->forceFill(['register_document_path' => 'wholesale/registers/crawl.pdf', 'register_document_name' => 'cr.pdf'])->save();
        $this->actingAs($this->merchantUser)->post($this->siteUrl('/ar/wholesale/catalog-pdf/shares'), [])->assertSessionHasNoErrors();
        $share = CatalogShare::query()->where('merchant_id', $merchant->id)->latest('id')->firstOrFail();
        $abandoned = Cart::query()->whereNotNull('contact_email')->whereNull('order_id')->firstOrFail();
        $page = Page::query()->firstOrFail();

        $this->values = [
            'product' => $demo->products['oxford-shirt']->id,
            'order' => $demo->orders['delivered-old']->id,
            'category' => $demo->categories['shirts']->id,
            'attribute' => $demo->colors['black']->attribute_id,
            'addon' => $addon->id,
            'brand' => $brand->id,
            'label' => $label->id,
            'tag' => $tag->id,
            'offer' => Offer::query()->value('id'),
            'currency' => Currency::query()->value('id'),
            'city' => $demo->cities['center']->id,
            'country' => Country::query()->value('id'),
            'district' => District::query()->value('id'),
            'governorate' => Governorate::query()->value('id'),
            'branch' => $demo->branch->id,
            'user' => $this->customer->id,
            'zone' => $demo->zones['center']->id,
            'post' => $post->id,
            'slide' => $slide->id,
            'review' => Review::query()->value('id'),
            'returnRequest' => $return->id,
            'merchant' => $merchant->id,
            'tier' => $demo->extra['tiers']['gold']->id,
            'bundle' => $demo->bundles['summer-set']->id,
            'cart' => $abandoned->id,
            'type' => 'products',
            'token' => 'crawl-token',
            'activity' => Activity::query()->value('id'),
            'slug' => $demo->products['oxford-shirt']->slug,
            'number' => $demo->orders['delivered-old']->order_number,
            'path' => 'no-such-page',
            'fallbackPlaceholder' => 'no-such-page',
        ];

        $this->byRoute = [
            'store.category' => ['slug' => $demo->categories['shirts']->slug],
            '{locale}/collections/{slug}' => ['slug' => $demo->categories['shirts']->slug],
            'store.page' => ['slug' => $page->slug],
            'api.api.v1.store.page.show' => ['slug' => $page->slug],
            'store.campaign' => ['slug' => $demo->extra['campaign']->slug],
            'campaign-pages.edit' => ['page' => $demo->extra['campaign']->id],
            'pages.edit' => ['page' => $page->id],
            'store.wholesale.orders.show' => ['number' => $demo->extra['wholesale_orders']['delivered']->order_number],
            'api.api.v1.item.order.show' => ['order' => $demo->orders['delivered-old']->id],
        ];

        // A signature covers the whole address (its language too): each is signed for Arabic and
        // opened as it is.
        $this->signed = [
            'store.cart.restore' => $this->siteUrl(URL::temporarySignedRoute('store.cart.restore', now()->addDay(), ['locale' => 'ar', 'cart' => $abandoned->uuid], absolute: false)),
            'store.unsubscribe' => app(OptOutService::class)->url('email', 'crawl@example.com', 'ar'),
            'merchants.document' => URL::temporarySignedRoute('merchants.document', now()->addHour(), ['locale' => 'ar', 'merchant' => $merchant->id]),
            'merchants.receipt' => URL::temporarySignedRoute('merchants.receipt', now()->addHour(), ['locale' => 'ar', 'merchant' => $merchant->id, 'entry' => $receipt->id]),
            'store.wholesale.catalog-pdf.shared' => $share->url(),
        ];
    }
}
