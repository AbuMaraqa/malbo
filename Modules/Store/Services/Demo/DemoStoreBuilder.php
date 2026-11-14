<?php

namespace Modules\Store\Services\Demo;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Branch\Enums\BranchStatus;
use Modules\Branch\Services\BranchService;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Support\Translations;
use Modules\GeoLocation\Entities\City;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Enums\BundleType;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Enums\OfferType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Enums\ShipmentStatus;
use Modules\Items\Services\AttributeService;
use Modules\Items\Services\BundleService;
use Modules\Items\Services\CategoryService;
use Modules\Items\Services\OfferService;
use Modules\Items\Services\OrderService;
use Modules\Items\Services\ProductService;
use Modules\Items\Services\ShipmentService;
use Modules\Store\Contracts\DemoPart;
use Modules\Store\Entities\CampaignPage;
use Modules\Store\Entities\ShippingZone;
use Modules\Store\Enums\ReviewStatus;
use Modules\Store\Services\AbandonedCartService;
use Modules\Store\Services\CampaignPageService;
use Modules\Store\Services\CartService;
use Modules\Store\Services\CheckoutService;
use Modules\Store\Services\InstagramService;
use Modules\Store\Services\ReviewService;
use Modules\Store\Services\ShippingZoneService;
use Modules\Store\Services\StockAlertService;
use Modules\Store\Support\CartCookie;

/**
 * Builds the demo store (`php artisan malbo:demo`) through the same services the admin and the
 * storefront use: the catalog through ProductService (opening stock is an INCOMING movement),
 * sets through BundleService, orders through the cart and CheckoutService, then moved by
 * OrderService and ShipmentService as staff would. Other modules add their part (`malbo.demo`
 * tagged DemoParts). Never touches a row it did not make.
 */
final class DemoStoreBuilder
{
    /** The locales the texts come in, in the catalog's order. */
    private const LOCALES = ['ar', 'en', 'he'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly CategoryService $categories,
        private readonly AttributeService $attributes,
        private readonly ProductService $products,
        private readonly BundleService $bundles,
        private readonly ShippingZoneService $zones,
        private readonly OfferService $offers,
        private readonly BranchService $branches,
        private readonly CartService $cart,
        private readonly CheckoutService $checkout,
        private readonly OrderService $orders,
        private readonly ShipmentService $shipments,
        private readonly ReviewService $reviews,
        private readonly StockAlertService $stockAlerts,
        private readonly AbandonedCartService $abandoned,
        private readonly CampaignPageService $campaigns,
        private readonly InstagramService $instagram,
    ) {}

    /**
     * Whether a demo store was built here already (its account exists).
     */
    public function exists(): bool
    {
        return $this->users->findByEmail(DemoStore::BOT_EMAIL) !== null;
    }

    public function build(bool $images = true, ?string $password = null): DemoStore
    {
        $demo = new DemoStore(
            images: $images && DemoImages::available(),
            password: $password ?? (string) config('store.demo.password', 'password'),
            staffId: User::role(UserRole::SUPER_ADMIN->value)->orderBy('id')->value('id'),
            now: Carbon::now(),
        );

        // Storefront code links to pages in the current language, as inside a request.
        $locale = app()->getLocale();
        app()->setLocale('ar');
        URL::defaults(['locale' => 'ar']);

        try {
            // All or nothing: a failure leaves no half-built store behind (pictures already written
            // to disk stay there).
            DB::transaction(function () use ($demo): void {
                $demo->bot = $this->account('MALBO demo', DemoStore::BOT_EMAIL, Str::random(40), null);
                $this->catalog($demo);
                $this->sets($demo);
                $this->shipping($demo);
                $this->coupon($demo);
                $this->customers($demo);
                $this->orders($demo);
                $this->marketing($demo);

                foreach (app()->tagged('malbo.demo') as $part) {
                    /** @var DemoPart $part */
                    $part->build($demo);
                }
            });
        } finally {
            app()->setLocale($locale);
        }

        return $demo;
    }

    // ---------------------------------------------------------------- catalog

    private function catalog(DemoStore $demo): void
    {
        foreach (DemoCatalog::CATEGORIES as $key => [$ar, $en, $he, $parent]) {
            $demo->categories[$key] = $this->categories->create(
                ['slug' => $this->freeSlug((new Category)->getTable(), $key), 'parent_id' => $parent ? $demo->categories[$parent]->id : null, 'status' => CategoryStatus::ACTIVE->value, 'sort_order' => count($demo->categories)],
                self::texts(['name' => [$ar, $en, $he]]),
                $demo->images && $parent !== null ? DemoImages::make($key, '#2B3A55', 600, 600) : null,
                $demo->bot->id,
            );
        }

        $color = $this->attribute($demo, 'color', ['اللون', 'Colour', 'צבע'], AttributeType::COLOR, array_map(
            fn (array $row) => ['names' => array_slice($row, 0, 3), 'color_code' => $row[3]],
            DemoCatalog::COLORS,
        ));
        // The size is one attribute in the store: the demo's only when there is none yet.
        $hasSize = Attribute::query()->where('is_size', true)->exists();
        $size = $this->attribute($demo, 'size', ['المقاس', 'Size', 'מידה'], AttributeType::SELECT, array_combine(
            DemoCatalog::SIZES,
            array_map(fn (string $size) => ['names' => [$size, $size, $size], 'color_code' => null], DemoCatalog::SIZES),
        ), isSize: ! $hasSize);

        $demo->colors = $color;
        $demo->sizes = $size;

        foreach (DemoCatalog::products() as $key => $row) {
            $this->product($demo, $key, $row);
        }
    }

    /**
     * @param  array{0: string, 1: string, 2: string}  $names
     * @param  array<string, array{names: array<int, string>, color_code: ?string}>  $values
     * @return array<string, \Modules\Items\Entities\AttributeValue> by key
     */
    private function attribute(DemoStore $demo, string $slug, array $names, AttributeType $type, array $values, bool $isSize = false): array
    {
        $attribute = $this->attributes->create([
            'slug' => $this->freeSlug((new Attribute)->getTable(), $slug),
            'type' => $type->value,
            'is_filterable' => true,
            'is_size' => $isSize,
            'name' => $names[0],
            'values' => array_values(array_map(fn (array $value) => ['name' => $value['names'][0], 'color_code' => $value['color_code']], $values)),
        ], 'ar', $demo->bot->id);

        Translations::fill($attribute, ['en' => ['name' => $names[1]], 'he' => ['name' => $names[2]]]);
        $attribute->save();

        $saved = $attribute->attributeValues()->orderBy('sort_order')->get()->values();
        $byKey = [];

        foreach (array_keys($values) as $index => $key) {
            $value = $saved[$index];
            Translations::fill($value, ['en' => ['name' => $values[$key]['names'][1]], 'he' => ['name' => $values[$key]['names'][2]]]);
            $value->save();
            $byKey[(string) $key] = $value;
        }

        return $byKey;
    }

    /**
     * @param  array<string, mixed>  $row  DemoCatalog::products()
     */
    private function product(DemoStore $demo, string $key, array $row): void
    {
        $material = DemoCatalog::MATERIALS[$row['material']];
        $fit = $row['fit'] ? DemoCatalog::FITS[$row['fit']] : null;
        $flags = $row['flags'];
        $sku = 'MLB-'.strtoupper(implode('', array_map(fn (string $word) => substr($word, 0, 3), explode('-', $key))));
        $hasVariants = $row['colors'] !== [] || $row['sizes'] !== [];

        $texts = self::texts([
            'name' => $row['names'],
            'short_description' => $row['short'],
            'description' => array_map(fn (string $short, string $fabric) => "<p>{$short}.</p><p>{$fabric}.</p>", $row['short'], $material['fabric']),
            'fabric' => $material['fabric'],
            'composition' => $material['composition'],
            'care' => $material['care'],
            ...($fit ? ['fit' => $fit] : []),
        ]);

        $variations = [];

        foreach ($row['colors'] ?: [null] as $color) {
            foreach ($row['sizes'] ?: [null] as $size) {
                $itemKey = implode('.', array_filter([$key, $color, $size], fn ($part) => $part !== null));
                $variations[] = [
                    'sku' => $sku.'-'.strtoupper(implode('-', array_filter([$color ? substr($color, 0, 3) : null, $size]))),
                    'base_price' => $row['price'],
                    'sale_price' => $row['sale'],
                    'manage_stock' => true,
                    'stock_quantity' => $row['stock'] ?? DemoCatalog::stockFor($itemKey),
                    'low_stock_threshold' => 3,
                    'is_active' => 1,
                    'attribute_values' => array_values(array_filter([$color ? $demo->colors[$color]->id : null, $size ? $demo->sizes[$size]->id : null])),
                    '_key' => $itemKey,
                ];
            }
        }

        $data = [
            ...$texts['ar'],
            'slug' => $this->freeSlug((new Product)->getTable(), $key),
            'sku' => $sku,
            'base_price' => $row['price'],
            'sale_price' => $row['sale'],
            'wholesale_price' => $row['wholesale'],
            'status' => ProductStatus::PUBLISHED->value,
            'is_active' => 1,
            'is_new' => in_array('new', $flags, true),
            'is_best_seller' => in_array('best', $flags, true),
            'is_featured' => in_array('featured', $flags, true),
            'model_height' => $row['model'][0] ?? null,
            'model_weight' => $row['model'][1] ?? null,
            'model_size' => $row['model'][2] ?? null,
            'category_id' => [$demo->categories[$row['category']]->id],
            ...($hasVariants ? [
                'manage_stock' => false,
                'variations' => array_map(fn (array $variation) => array_diff_key($variation, ['_key' => true]), $variations),
            ] : [
                'manage_stock' => true,
                'stock_quantity' => $row['stock'] ?? 10,
                'low_stock_threshold' => 3,
            ]),
            ...($demo->images ? ['image' => DemoImages::make($key, DemoCatalog::COLORS[$row['colors'][0] ?? 'navy'][3])] : []),
        ];

        $product = $this->products->create($data, 'ar', $demo->bot->id);
        Translations::fill($product, ['en' => $texts['en'], 'he' => $texts['he']]);
        $product->save();
        $demo->products[$key] = $product;

        if (! $hasVariants) {
            $demo->items[$key] = $product;

            return;
        }

        $saved = $product->variations()->with('attributeValues')->get();

        foreach ($variations as $variation) {
            $variant = $saved->firstWhere('sku', $variation['sku']);
            $parts = explode('.', $variation['_key']);
            $color = $row['colors'] !== [] ? $parts[1] : null;
            $size = $row['sizes'] !== [] ? end($parts) : null;

            Translations::fill($variant, [
                'en' => ['name' => self::variantName($row['names'][1], $color ? DemoCatalog::COLORS[$color][1] : null, $size)],
                'he' => ['name' => self::variantName($row['names'][2], $color ? DemoCatalog::COLORS[$color][2] : null, $size)],
            ]);
            $variant->save();
            $demo->items[$variation['_key']] = $variant;
        }
    }

    /**
     * A set sold as its own product, and a bundle offer at a percentage off.
     */
    private function sets(DemoStore $demo): void
    {
        $sets = [
            'summer-set' => [
                'type' => BundleType::SET, 'names' => ['طقم الصيف: قميص كتان وتشينو', 'Summer set: linen shirt and chinos', "סט קיץ: חולצת פשתן וצ'ינו"],
                'short' => ['قطعتان بسعر خاص، اختر مقاسك لكل قطعة', 'Two pieces at a special price; pick your size for each', 'שני פריטים במחיר מיוחד; בחרו מידה לכל פריט'],
                'pricing' => 'fixed', 'price' => 279, 'components' => ['linen-shirt', 'chinos'], 'category' => 'shirts', 'new' => true,
            ],
            'office-bundle' => [
                'type' => BundleType::BUNDLE, 'names' => ['عرض المكتب: قميص وربطة عنق وحزام', 'Office offer: shirt, tie and belt', 'מבצע משרד: חולצה, עניבה וחגורה'],
                'short' => ['خصم 15% عند شراء القطع الثلاث معاً', '15% off when you buy all three together', '15% הנחה בקנייה של שלושתם יחד'],
                'pricing' => 'percent', 'percent' => 15, 'components' => ['oxford-shirt', 'silk-tie', 'leather-belt'], 'category' => 'accessories', 'new' => false,
            ],
        ];

        foreach ($sets as $key => $row) {
            $texts = self::texts(['name' => $row['names'], 'short_description' => $row['short']]);

            $bundle = $this->bundles->create([
                ...$texts['ar'],
                'bundle_type' => $row['type']->value,
                'slug' => $this->freeSlug((new Product)->getTable(), $key),
                'sku' => 'MLB-'.strtoupper($key),
                'status' => ProductStatus::PUBLISHED->value,
                'is_active' => true,
                'is_new' => $row['new'],
                'is_featured' => true,
                'category_id' => [$demo->categories[$row['category']]->id],
                'pricing' => $row['pricing'],
                'base_price' => $row['price'] ?? null,
                'bundle_discount_percent' => $row['percent'] ?? null,
                'components' => array_map(fn (string $component) => ['product_id' => $demo->products[$component]->id, 'quantity' => 1], $row['components']),
                ...($demo->images ? ['image' => DemoImages::make($key, '#1F2A44')] : []),
            ], 'ar', $demo->bot->id);

            Translations::fill($bundle, ['en' => $texts['en'], 'he' => $texts['he']]);
            $bundle->save();
            $demo->bundles[$key] = $bundle;
        }
    }

    // ---------------------------------------------------------------- shipping, coupon, people

    private function shipping(DemoStore $demo): void
    {
        $zones = [
            'center' => [['الوسط', 'Center', 'מרכז'], 20, 1, 2, 300, ['al-Bireh', 'Beituniya', 'Jerusalem', 'Abu Dis', 'al-Azariyah', 'Bethlehem', 'Beit Jala', 'Beit Sahour', 'Jericho', 'Ramallah']],
            'north' => [['الشمال', 'North', 'צפון'], 30, 2, 3, 400, ['Nablus', 'Jenin', 'Tulkarm', 'Qalqilya', 'Salfit', 'Tubas', 'Anabta', 'Qabatiya']],
            'south' => [['الجنوب', 'South', 'דרום'], 30, 2, 4, 400, ['Hebron', 'Dura', 'Yatta']],
        ];

        // Cities already in another active zone stay there.
        $zone = new ShippingZone;
        $pivot = $zone->cities()->getTable();
        $taken = DB::table($pivot)
            ->join($zone->getTable(), $zone->getQualifiedKeyName(), '=', $pivot.'.'.$zone->cities()->getForeignPivotKeyName())
            ->where($zone->qualifyColumn('is_active'), true)
            ->pluck($pivot.'.'.$zone->cities()->getRelatedPivotKeyName())->map(fn ($id) => (int) $id)->all();

        foreach ($zones as $key => [$names, $fee, $min, $max, $free, $cityNames]) {
            $cities = City::query()->whereHas('translations', fn ($names) => $names->where('locale', 'en')->whereIn('name', $cityNames))->whereKeyNot($taken)->orderBy('id')->get();

            if ($cities->isEmpty()) {
                // Another country's seed: the next free cities.
                $cities = City::query()->whereKeyNot([...$taken, ...array_map(fn (City $city) => $city->id, $demo->cities)])->orderBy('id')->limit(3)->get();
            }

            if ($cities->isEmpty()) {
                $demo->notes[] = "Shipping zone {$names[1]} skipped: no free city.";

                continue;
            }

            $zone = $this->zones->create([
                'name' => $names[0], 'fee' => $fee, 'eta_min_days' => $min, 'eta_max_days' => $max,
                'free_shipping_threshold' => $free, 'is_active' => true, 'sort_order' => count($demo->zones),
                'cities' => $cities->modelKeys(),
            ], 'ar', $demo->bot->id);
            Translations::fill($zone, ['en' => ['name' => $names[1]], 'he' => ['name' => $names[2]]]);
            $zone->save();

            $taken = [...$taken, ...$cities->modelKeys()];
            $demo->zones[$key] = $zone;
            $demo->cities[$key] = $cities->first();
        }
    }

    private function coupon(DemoStore $demo): void
    {
        // An offer belongs to a branch (the online store ignores it): the demo's own.
        $demo->branch = $this->branches->create([
            'name' => 'معرض MALBO - رام الله',
            'address' => 'شارع الإرسال',
            'phone' => '022950000',
            'email' => $demo->email('branch'),
            'city_id' => $demo->cities['center']->id ?? City::query()->value('id'),
            'country_id' => ($demo->cities['center'] ?? City::query()->first())?->governorate?->country_id,
            'opening_time' => '09:00',
            'closing_time' => '21:00',
            'status' => BranchStatus::OPEN->value,
        ], 'ar', $demo->bot->id);
        Translations::fill($demo->branch, ['en' => ['name' => 'MALBO showroom - Ramallah'], 'he' => ['name' => 'אולם התצוגה של MALBO - רמאללה']]);
        $demo->branch->save();

        if ($this->offerCodeTaken($demo->coupon)) {
            $demo->coupon = 'MALBODEMO10';
        }

        [$offer] = $this->offers->create([
            'branch_id' => [$demo->branch->id],
            'name' => 'خصم الترحيب 10%',
            'description' => 'خصم 10% على كل الملابس الرجالية',
            'type' => OfferType::PERCENTAGE->value,
            'value' => 10,
            'code' => $demo->coupon,
            'min_purchase_amount' => 100,
            'usage_limit' => 500,
            'usage_limit_per_user' => 1,
            'start_date' => Carbon::now()->subMonth()->toDateString(),
            'end_date' => Carbon::now()->addYear()->toDateString(),
            'is_active' => true,
            'offerable_model' => 'items_category',
            'offerable_id' => $demo->categories['men']->id,
        ], 'ar', $demo->bot->id);
        Translations::fill($offer, ['en' => ['name' => 'Welcome 10% off'], 'he' => ['name' => '10% הנחת פתיחה']]);
        $offer->save();
    }

    private function customers(DemoStore $demo): void
    {
        foreach ([
            'rami' => ['رامي خليل', '0599100200'],
            'yousef' => ['يوسف حداد', '0569300400'],
        ] as $key => [$name, $phone]) {
            $user = $this->account($name, $demo->email($key), $demo->password, UserRole::CUSTOMER, $phone);
            $demo->customers[$key] = $user;
            $demo->logins[] = ['Customer (B2C)', $user->email, $demo->password];
        }
    }

    private function account(string $name, string $email, string $password, ?UserRole $role, ?string $phone = null): User
    {
        /** @var User $user */
        $user = $this->users->create(['name' => $name, 'email' => $email, 'phone' => $phone, 'password' => $password]);
        $user->forceFill(['email_verified_at' => Carbon::now()])->save();

        if ($role !== null) {
            $user->assignRole($role->value);
        }

        return $user;
    }

    // ---------------------------------------------------------------- orders

    /**
     * Storefront orders over the past three weeks, at every stage, so the dashboard, the orders
     * list, shipments, returns and reviews have something to show.
     */
    private function orders(DemoStore $demo): void
    {
        $rami = $demo->customers['rami'];
        $yousef = $demo->customers['yousef'];

        // Delivered three weeks ago and two weeks ago (reviews), and six days ago (returns still open).
        $demo->orders['delivered-old'] = $this->order($demo, 21, $rami, 'center', [['oxford-shirt', 'white', 'M', 1], ['chinos', 'beige', '32', 1]]);
        $this->deliver($demo, $demo->orders['delivered-old'], 21);

        $demo->orders['delivered-polo'] = $this->order($demo, 14, $yousef, 'north', [['polo', 'navy', 'L', 2]]);
        $this->deliver($demo, $demo->orders['delivered-polo'], 14);

        $demo->orders['delivered-recent'] = $this->order($demo, 6, $rami, 'center', [['slim-jeans', 'navy', '32', 1], ['crew-tee', 'black', 'M', 2]]);
        $this->deliver($demo, $demo->orders['delivered-recent'], 6);

        // A set, delivered.
        $demo->orders['set'] = $this->order($demo, 9, $yousef, 'north', [['summer-set', ['linen-shirt' => ['white', 'L'], 'chinos' => ['navy', '32']], 1]]);
        $this->deliver($demo, $demo->orders['set'], 9);

        // With the courier, being prepared, confirmed.
        $demo->orders['with-courier'] = $this->order($demo, 3, $yousef, 'south', [['bomber', 'black', 'L', 1]]);
        $this->advance($demo, $demo->orders['with-courier'], 3, [OrderStatus::CONFIRMED, OrderStatus::PREPARING], ShipmentStatus::PICKED_UP);

        $demo->orders['preparing'] = $this->order($demo, 2, null, 'center', [['navy-suit', 'navy', '50', 1]], guest: ['name' => 'سامر عودة', 'phone' => '0598700800', 'email' => $demo->email('samer')]);
        $this->advance($demo, $demo->orders['preparing'], 2, [OrderStatus::CONFIRMED, OrderStatus::PREPARING]);

        $demo->orders['confirmed'] = $this->order($demo, 1, $rami, 'center', [['linen-blazer', 'beige', '48', 1]]);
        $this->advance($demo, $demo->orders['confirmed'], 1, [OrderStatus::CONFIRMED]);

        // Cancelled by the store: its stock came back.
        $demo->orders['cancelled'] = $this->order($demo, 4, null, 'north', [['flannel-shirt', 'grey', 'M', 1]], guest: ['name' => 'ليث ناصر', 'phone' => '0597111222', 'email' => $demo->email('laith')]);
        $this->advance($demo, $demo->orders['cancelled'], 4, [OrderStatus::CANCELLED]);

        // New today, awaiting confirmation, with the welcome coupon and free delivery.
        $demo->orders['awaiting'] = $this->order($demo, 0.05, null, 'center', [['oxford-shirt', 'sky', 'L', 2], ['silk-tie', null, null, 1]], guest: ['name' => 'أحمد سليم', 'phone' => '0599888777', 'email' => $demo->email('ahmad')], coupon: $demo->coupon);
        $demo->orders['awaiting-bundle'] = $this->order($demo, 0.02, $yousef, 'north', [['office-bundle', ['oxford-shirt' => ['navy', 'XL'], 'leather-belt' => ['black', null], 'silk-tie' => [null, null]], 1]]);

        // Reviews from the buyers who received their goods, approved by staff.
        foreach ([
            ['rami', 'oxford-shirt', 5, 'قميص ممتاز', 'القماش ممتاز والمقاس مضبوط تماماً.'],
            ['rami', 'chinos', 4, 'مريح جداً', 'بنطال مريح للعمل، اللون مطابق للصور.'],
            ['yousef', 'polo', 5, 'Great polo', 'Soft piqué and the collar keeps its shape after washing.'],
        ] as [$customer, $product, $rating, $title, $body]) {
            $review = $this->reviews->submit($demo->customers[$customer], $demo->products[$product], ['rating' => $rating, 'title' => $title, 'body' => $body]);
            $this->reviews->moderate($review, ReviewStatus::APPROVED);
        }
    }

    /**
     * An order placed on the storefront: a cart filled line by line, then checkout (cash on delivery).
     * A line is [product key, colour, size, quantity], or [set key, [component key => [colour, size]], quantity].
     *
     * @param  list<array<int, mixed>>  $lines
     * @param  array{name: string, phone: string, email: string}|null  $guest
     */
    private function order(DemoStore $demo, float $daysAgo, ?User $user, string $zone, array $lines, ?array $guest = null, ?string $coupon = null): Order
    {
        return $demo->at($daysAgo, function () use ($demo, $user, $zone, $lines, $guest, $coupon): Order {
            $request = Request::create('/ar/cart', 'POST');
            $request->setUserResolver(fn () => $user);
            $cart = null;

            foreach ($lines as $line) {
                if (is_array($line[1])) {
                    [$key, $choices, $quantity] = $line;
                    $bundle = $demo->bundles[$key]->load('bundleComponents');
                    $components = [];

                    foreach ($bundle->bundleComponents as $component) {
                        $piece = array_search((int) $component->product_id, array_map(fn (Product $product) => (int) $product->id, $demo->products), true);
                        [$color, $size] = $choices[$piece];
                        $components[$component->id] = $demo->item($piece, $color, $size)->id;
                    }

                    $cart = $this->cart->add($request, $bundle->id, $quantity, $components);
                } else {
                    [$key, $color, $size, $quantity] = $line;
                    $cart = $this->cart->add($request, $demo->item($key, $color, $size)->id, $quantity);
                }

                $request->cookies->set(CartCookie::NAME, $cart->token);
            }

            $city = $demo->cities[$zone];
            $buyer = $guest ?? ['name' => $user->name, 'phone' => $user->phone, 'email' => $user->email];

            return $this->checkout->place($cart, [
                'name' => $buyer['name'],
                'phone' => $buyer['phone'],
                'email' => $buyer['email'],
                'city_id' => $city->id,
                'district_id' => $city->districts()->value('id'),
                'address' => 'شارع النزهة، عمارة 12',
                'notes' => null,
                'coupon' => $coupon,
                'payment_method' => CheckoutService::CASH,
                'save_address' => $user !== null,
                'address_label' => 'البيت',
            ], $user);
        });
    }

    /**
     * Confirmed and prepared by staff, sent with the store's own courier and delivered (the
     * shipment moves the order).
     */
    private function deliver(DemoStore $demo, Order $order, float $daysAgo): void
    {
        $this->advance($demo, $order, $daysAgo, [OrderStatus::CONFIRMED, OrderStatus::PREPARING], ShipmentStatus::DELIVERED);
    }

    /**
     * @param  list<OrderStatus>  $statuses  the order's moves, a few hours apart
     */
    public function advance(DemoStore $demo, Order $order, float $daysAgo, array $statuses, ?ShipmentStatus $shipment = null): void
    {
        $step = 0;

        foreach ($statuses as $status) {
            $demo->at(max(0, $daysAgo - (++$step) * 0.1), fn () => $this->orders->changeStatus($order, $status, $demo->bot->id), hour: 12 + $step);
        }

        if ($shipment === null) {
            return;
        }

        $made = $demo->at(max(0, $daysAgo - 0.3), fn () => $this->shipments->create($order, ['courier' => 'manual', 'tracking_number' => 'MLB'.$order->id.'00', 'cost' => 12], $demo->bot->id), hour: 15);
        $moves = $shipment === ShipmentStatus::DELIVERED ? [ShipmentStatus::PICKED_UP, ShipmentStatus::DELIVERED] : [$shipment];

        foreach ($moves as $index => $move) {
            $demo->at(max(0, $daysAgo - 0.4 - $index), fn () => $this->shipments->move($made, $move, null, $demo->bot->id), hour: 16 + $index);
        }
    }

    // ---------------------------------------------------------------- marketing

    private function marketing(DemoStore $demo): void
    {
        // Someone waits for the sold-out scarf.
        $this->stockAlerts->subscribe($demo->products['wool-scarf']->id, $demo->email('waiting'), null, 'ar');

        // A cart left three hours ago, its buyer agreed to a reminder.
        $demo->at(0.125, function () use ($demo): void {
            $request = Request::create('/ar/cart', 'POST');
            $cart = $this->cart->add($request, $demo->item('bomber', 'olive', 'M')->id, 1);
            $this->abandoned->saveContact($cart, true, $demo->email('left-cart'), null, 'ar');
        });

        // A published campaign page, in the three languages.
        $blocks = fn (array $texts) => [
            ['type' => 'text', 'content' => ['html' => "<h2>{$texts[0]}</h2><p>{$texts[1]}</p>"]],
            ['type' => 'products', 'settings' => ['source' => 'picked', 'product_ids' => [$demo->products['linen-shirt']->id, $demo->products['linen-blazer']->id, $demo->products['slim-jeans']->id, $demo->bundles['summer-set']->id]], 'content' => ['heading' => $texts[2]]],
            ['type' => 'cta', 'settings' => ['link' => '/'.$texts[4].'/shop'], 'content' => ['heading' => $texts[3], 'text' => $texts[1], 'button_label' => $texts[5]]],
        ];
        $locales = [
            'ar' => ['تخفيضات الصيف', 'خصومات حتى 25% على الكتان والجينز', 'مختارات الصيف', 'تسوق الآن', 'ar', 'تسوق'],
            'en' => ['Summer sale', 'Up to 25% off linen and denim', 'Summer picks', 'Shop now', 'en', 'Shop'],
            'he' => ['מבצע קיץ', 'עד 25% הנחה על פשתן וג׳ינס', 'הבחירות של הקיץ', 'קנו עכשיו', 'he', 'לחנות'],
        ];

        $page = $this->campaigns->create([
            'title' => $locales['ar'][0], 'slug' => $this->freeSlug((new CampaignPage)->getTable(), 'summer-sale'),
            'status' => CampaignPage::PUBLISHED, 'blocks' => $blocks($locales['ar']),
        ], 'ar', $demo->bot->id);

        foreach (['en', 'he'] as $locale) {
            $ids = $page->blocks()->orderBy('sort_order')->pluck('id')->all();
            $this->campaigns->update($page, [
                'title' => $locales[$locale][0], 'slug' => $page->slug, 'status' => CampaignPage::PUBLISHED,
                'blocks' => array_map(fn (array $block, int $id) => ['id' => $id, ...$block], $blocks($locales[$locale]), $ids),
            ], $locale);
        }

        $demo->extra['campaign'] = $page->fresh();

        if ($demo->images) {
            foreach (['oxford-shirt', 'linen-shirt', 'navy-suit', 'bomber'] as $index => $key) {
                $this->instagram->create([
                    'permalink' => 'https://www.instagram.com/p/MALBODEMO'.($index + 1).'/',
                    'caption' => $demo->products[$key]->translate('ar')?->name,
                    'posted_at' => Carbon::now()->subDays(3 * $index + 1)->toDateTimeString(),
                    'sort_order' => $index,
                    'is_active' => true,
                    'products' => [$demo->products[$key]->id],
                    'image' => DemoImages::make('instagram-'.$key, DemoCatalog::COLORS[DemoCatalog::products()[$key]['colors'][0]][3], 1080, 1080),
                ], $demo->bot->id);
            }

            $this->instagram->saveSettings(true, 'تابعونا على إنستغرام', 'ar');
        }
    }

    // ---------------------------------------------------------------- helpers

    /**
     * locale => [field => text] from [field => [ar, en, he]].
     *
     * @param  array<string, array<int, string>>  $fields
     * @return array<string, array<string, string>>
     */
    private static function texts(array $fields): array
    {
        $texts = [];

        foreach (self::LOCALES as $index => $locale) {
            foreach ($fields as $field => $values) {
                $texts[$locale][$field] = $values[$index];
            }
        }

        return $texts;
    }

    private static function variantName(string $name, ?string $color, ?string $size): string
    {
        return implode(' - ', array_filter([$name, $color, $size], fn ($part) => $part !== null));
    }

    /**
     * $slug, or $slug-2, -3... when a row (one the demo did not make, or a deleted one) has it.
     */
    private function freeSlug(string $table, string $slug): string
    {
        $candidate = $slug;

        for ($n = 2; DB::table($table)->where('slug', $candidate)->exists(); $n++) {
            $candidate = $slug.'-'.$n;
        }

        return $candidate;
    }

    private function offerCodeTaken(string $code): bool
    {
        return DB::table((new \Modules\Items\Entities\Offer)->getTable())->whereRaw('lower(code) = ?', [mb_strtolower($code)])->exists();
    }
}
