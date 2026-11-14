<?php

namespace Modules\Store\Services\Demo;

use Closure;
use Illuminate\Support\Carbon;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Store\Entities\ShippingZone;

/**
 * What `malbo:demo` built, as the builder and the modules' DemoParts fill it in. Every row it
 * writes is made by the demo account (`created_by`), and every account it opens has an e-mail
 * address at DOMAIN: that is how a second run knows the demo is already there.
 */
final class DemoStore
{
    public const DOMAIN = 'demo.malbo.test';

    public const BOT_EMAIL = 'demo-bot@'.self::DOMAIN;

    public User $bot;

    /** @var array<string, Category> */
    public array $categories = [];

    /** @var array<string, AttributeValue> */
    public array $colors = [];

    /** @var array<string, AttributeValue> */
    public array $sizes = [];

    /** @var array<string, Product> parents (or single products) by catalog key */
    public array $products = [];

    /** @var array<string, Product> sellable items by "key", "key.colour" or "key.colour.size" */
    public array $items = [];

    /** @var array<string, Product> sets and bundle offers by key */
    public array $bundles = [];

    /** @var array<string, ShippingZone> */
    public array $zones = [];

    /** @var array<string, City> one city of each zone, by the zone's key */
    public array $cities = [];

    public ?Branch $branch = null;

    public string $coupon = 'WELCOME10';

    /** @var array<string, User> */
    public array $customers = [];

    /** @var array<string, Order> */
    public array $orders = [];

    /** @var array<string, mixed> what other modules' parts made (merchants, tiers...) */
    public array $extra = [];

    /** @var list<array{0: string, 1: string, 2: string}> [who, e-mail, password] */
    public array $logins = [];

    /** @var list<string> */
    public array $notes = [];

    public function __construct(
        public readonly bool $images,
        public readonly string $password,
        public readonly ?int $staffId,
        private readonly Carbon $now,
    ) {}

    /**
     * The sellable item of a product: its colour and size variant, or the product itself.
     */
    public function item(string $key, ?string $color = null, ?string $size = null): Product
    {
        $name = implode('.', array_filter([$key, $color, $size], fn ($part) => $part !== null));

        return $this->items[$name] ?? throw new \OutOfBoundsException("No demo item {$name}.");
    }

    public function email(string $name): string
    {
        return $name.'@'.self::DOMAIN;
    }

    /**
     * Runs $write as if it were $daysAgo days ago (at $hour), so orders and their history spread over
     * the past weeks; the clock is put back afterwards.
     *
     * @template T
     *
     * @param  Closure(): T  $write
     * @return T
     */
    public function at(float $daysAgo, Closure $write, int $hour = 11): mixed
    {
        $previous = Carbon::hasTestNow() ? Carbon::getTestNow() : null;
        $when = $this->now->copy()->subMinutes((int) round($daysAgo * 1440));

        if ($daysAgo >= 1) {
            $when->setTime($hour, (int) ($daysAgo * 7) % 60);
        }

        Carbon::setTestNow($when);

        try {
            return $write();
        } finally {
            Carbon::setTestNow($previous);
        }
    }
}
