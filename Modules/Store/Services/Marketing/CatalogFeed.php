<?php

namespace Modules\Store\Services\Marketing;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Modules\Core\Settings\GeneralSettings;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Support\BundleStock;
use Modules\Store\Services\PricingService;
use Modules\Store\Services\ProductConfigurator;
use Modules\Store\Settings\MarketingSettings;
use Modules\Store\Support\CatalogIds;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use XMLWriter;

/**
 * The product catalog for Facebook and Instagram (Meta Commerce Manager), as RSS 2.0 with the `g:`
 * namespace or as CSV. One item per variant a buyer can choose (active, no hidden value) of a
 * published, active product, or one per such product without variants. In Arabic, the store's
 * main language.
 *
 * Ids are the ones every Pixel and server event uses (CatalogIds). Prices are the storefront's
 * (PricingService): `price` is the base price, `sale_price` the discounted one while it applies.
 * Never a wholesale or cost price.
 *
 * Built at most once an hour, under its own key (not the catalog version: every sale changes that),
 * and again when a product is created, deleted or published (StoreServiceProvider).
 */
class CatalogFeed
{
    public const LOCALE = 'ar';

    public const CURRENCY = 'ILS';

    /** The CSV's columns, in order; the XML writes the same fields, empty ones left out. */
    public const COLUMNS = ['id', 'item_group_id', 'title', 'description', 'availability', 'condition', 'price', 'sale_price',
        'sale_price_effective_date', 'link', 'image_link', 'brand', 'color', 'size', 'product_type'];

    private const TTL_SECONDS = 3600;

    private const KEY = 'store.feed.meta-catalog';

    private const DESCRIPTION_LIMIT = 5000;

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly PricingService $pricing,
    ) {}

    /**
     * Whether a request may read the feed: always, unless the store set a token.
     */
    public function allows(mixed $token): bool
    {
        $expected = app(MarketingSettings::class)->feed_token;

        return blank($expected) || (is_string($token) && hash_equals((string) $expected, $token));
    }

    /**
     * @return list<array<string, string>>
     */
    public function items(): array
    {
        $items = Cache::get(self::KEY);

        if (is_array($items)) {
            return $items;
        }

        // One build at a time: the others wait for it and read what it stored.
        return Cache::lock(self::KEY.'.building', 120)->block(60, fn () => Cache::remember(self::KEY, self::TTL_SECONDS, fn () => $this->build()));
    }

    /**
     * Throws the feed away: a product appeared, went, or was published or hidden. Sales and price
     * edits wait for the hourly rebuild (Meta reads the feed hourly anyway).
     */
    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    public function xml(): string
    {
        $store = (string) app(GeneralSettings::class)->site_name;
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('rss');
        $writer->writeAttribute('version', '2.0');
        $writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $writer->startElement('channel');
        $writer->writeElement('title', self::clean($store));
        $writer->writeElement('link', route('home', ['locale' => self::LOCALE]));
        $writer->writeElement('description', self::clean($store));

        foreach ($this->items() as $item) {
            $writer->startElement('item');

            foreach ($item as $field => $value) {
                $writer->writeElement('g:'.$field, $value);
            }

            $writer->endElement();
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    public function csv(): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::COLUMNS, escape: '');

        foreach ($this->items() as $item) {
            fputcsv($handle, array_map(fn (string $column) => $item[$column] ?? '', self::COLUMNS), escape: '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @return list<array<string, string>>
     */
    private function build(): array
    {
        $previous = app()->getLocale();
        app()->setLocale(self::LOCALE);

        try {
            $brand = (string) app(GeneralSettings::class)->site_name;
            $items = [];

            $this->products->publishedForStorefront()
                ->with([
                    'translations', 'media', 'brand.translations',
                    'categories' => fn ($categories) => $categories->where('status', CategoryStatus::ACTIVE)->orderBy(config('items.table_prefix').'products_categories.id'),
                    'categories.translations',
                    'variations' => fn ($variations) => $variations->where('is_active', ProductActive::IS_ACTIVE->value)->orderBy('id'),
                    'variations.media',
                    'variations.attributeValues.translations',
                    'variations.attributeValues.attribute',
                    // A set or bundle is one item; its availability comes from its components.
                    ...BundleStock::relations(),
                ])
                ->chunkById(200, function (Collection $products) use (&$items, $brand): void {
                    foreach ($products as $product) {
                        $variants = $product->variations->filter(fn (Product $variant) => ProductConfigurator::visible($variant) && $variant->attributeValues->isNotEmpty());

                        if ($product->variations->isEmpty()) {
                            $items[] = $this->item($product, null, $brand);

                            continue;
                        }

                        foreach ($variants as $variant) {
                            $items[] = $this->item($product, $variant, $brand);
                        }
                    }
                });

            return $items;
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * @return array<string, string>
     */
    private function item(Product $parent, ?Product $variant, string $storeName): array
    {
        $item = $variant ?? $parent;
        $values = $variant?->attributeValues ?? collect();
        $color = $values->first(fn (AttributeValue $value) => (int) $value->attribute?->type === AttributeType::COLOR->value);
        $size = $values->first(fn (AttributeValue $value) => (bool) $value->attribute?->is_size);
        $price = $this->pricing->for($item);
        $base = round((float) $item->base_price, 2);
        $title = self::clean((string) $parent->name);

        $link = route('store.product', ['locale' => self::LOCALE, 'slug' => $parent->slug]);
        if ($variant !== null) {
            $link .= '?'.http_build_query(['selected' => $values->mapWithKeys(fn (AttributeValue $value) => [(int) $value->attribute_id => (int) $value->id])->sortKeys()->all()]);
        }

        $discounted = $price->discounted() && $price->amount < $base;

        return array_filter([
            'id' => CatalogIds::of($item),
            'item_group_id' => $variant ? CatalogIds::of($parent) : null,
            'title' => $title,
            'description' => self::plain((string) ($parent->short_description ?: $parent->description)) ?: $title,
            'availability' => ProductConfigurator::available($item) ? 'in stock' : 'out of stock',
            'condition' => 'new',
            'price' => self::money($base),
            'sale_price' => $discounted ? self::money($price->amount) : null,
            'sale_price_effective_date' => $discounted ? self::window($item) : null,
            'link' => $link,
            'image_link' => $this->image($parent, $variant, $color),
            'brand' => self::clean((string) ($parent->brand?->name ?: $storeName)),
            'color' => $color ? self::clean((string) $color->name) : null,
            'size' => $size ? self::clean((string) $size->name) : null,
            'product_type' => ($category = $parent->categories->first()) ? self::clean((string) $category->name) : null,
        ], fn (?string $value) => $value !== null && $value !== '');
    }

    /**
     * The color's own photo in the gallery, else the variant's image, else the product's.
     */
    private function image(Product $parent, ?Product $variant, ?AttributeValue $color): ?string
    {
        $colored = $color === null ? null : $parent->getMedia('product_gallery')
            ->first(fn (Media $media) => (int) $media->getCustomProperty('attribute_value_id') === (int) $color->id);

        $url = $colored?->getUrl() ?: ($variant?->getFirstMediaUrl('product') ?: $parent->getFirstMediaUrl('product'));

        if ($url === '') {
            return null;
        }

        return str_starts_with($url, 'http') ? $url : url($url);
    }

    /**
     * The dated discount's window ("start/end", ISO 8601), when the price comes from one.
     */
    private static function window(Product $item): ?string
    {
        if (! in_array($item->discount_type, ['percentage', 'fixed'], true) || (float) $item->discount_value <= 0
            || (! $item->discount_start_date && ! $item->discount_end_date)) {
            return null;
        }

        $start = $item->discount_start_date ? Carbon::parse($item->discount_start_date) : Carbon::now()->startOfDay();
        $end = $item->discount_end_date ? Carbon::parse($item->discount_end_date) : $start->copy()->addYear();

        // The admin picks a date: a midnight end means "through that day" (PricingService reads it so).
        if ($end->format('H:i:s') === '00:00:00') {
            $end = $end->endOfDay();
        }

        return self::date($start).'/'.self::date($end);
    }

    private static function date(CarbonInterface $date): string
    {
        return $date->format('Y-m-d\TH:iO');
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2, '.', '').' '.self::CURRENCY;
    }

    /**
     * Text from the editor's HTML: no tags (nor what scripts and styles held), entities decoded,
     * whitespace collapsed, at most 5000 characters.
     */
    private static function plain(string $html): string
    {
        $html = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = (string) preg_replace('#<(br|/p|/div|/li|/h\d)\b[^>]*>#i', ' $0', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(self::clean($text), 0, self::DESCRIPTION_LIMIT);
    }

    /**
     * One line of valid XML text: no control characters, single spaces.
     */
    private static function clean(string $text): string
    {
        $text = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
