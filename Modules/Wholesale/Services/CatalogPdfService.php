<?php

namespace Modules\Wholesale\Services;

use App\Support\Locales;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Settings\GeneralSettings;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Support\OrderDocument;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Support\Price;
use Modules\Wholesale\Entities\CatalogShare;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Repositories\Contracts\WholesaleProductRepositoryInterface;
use Modules\Wholesale\Support\PriceBook;
use Modules\Wholesale\Support\WholesaleItems;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Throwable;

/**
 * The PDF catalog a merchant shares on WhatsApp: the products the portal's catalog lists (at most
 * LIMIT), each with its image, name, colors and sizes and the suggested retail price. With
 * `wholesale` prices (the merchant's choice; `retail` is the default) also the merchant's own tier
 * price (the lowest of its items, "from" when they differ) and its quantity breaks. Prices come from
 * WholesalePricing for the merchant's tier only: never another tier's, never a cost price. Built in
 * one language (Arabic and Hebrew right to left) and drawn with mPDF.
 *
 * Memory: each image is a small JPEG thumbnail made once and kept on disk (THUMB_WIDTH wide, at
 * most MAX_IMAGE_BYTES), and the images of one PDF stop at a byte budget
 * (`wholesale.catalog_pdf.image_budget`, IMAGE_BUDGET by default). A drawn PDF is cached for
 * CACHE_SECONDS: per share link, and per merchant, category, language and prices for downloads.
 */
class CatalogPdfService
{
    public const LIMIT = 200;

    public const RETAIL = 'retail';

    public const WHOLESALE = 'wholesale';

    public const PRICES = [self::RETAIL, self::WHOLESALE];

    public const THUMB_WIDTH = 600;

    public const MAX_IMAGE_BYTES = 300 * 1024;

    public const IMAGE_BUDGET = 25 * 1024 * 1024;

    public const CACHE_SECONDS = 600;

    /** Pictures larger than this are not decoded (width × height): GD would need too much memory. */
    private const MAX_SOURCE_PIXELS = 25_000_000;

    public function __construct(
        private readonly WholesaleProductRepositoryInterface $products,
        private readonly WholesalePricing $pricing,
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    /**
     * What the PDF prints, as plain strings (the Blade view computes nothing).
     *
     * @return array<string, mixed>
     */
    public function document(Merchant $merchant, ?string $category, string $locale, string $prices = self::RETAIL, int $limit = self::LIMIT): array
    {
        $prices = in_array($prices, self::PRICES, true) ? $prices : self::RETAIL;

        return OrderDocument::inLocale($locale, function () use ($merchant, $category, $locale, $prices, $limit): array {
            $tier = $this->pricing->tierFor($merchant);
            $found = $this->products->forCatalogPdf($tier?->id, $category, $limit + 1);
            $trees = $found->take($limit);
            $book = $this->pricing->book($tier, $trees->flatMap(fn (Product $product) => WholesaleItems::of($product)));
            $budget = (int) (config('wholesale.catalog_pdf.image_budget') ?? self::IMAGE_BUDGET);
            $used = 0;
            $rows = [];

            foreach ($trees as $product) {
                if ($row = $this->product($product, $book, $prices === self::WHOLESALE, $budget, $used)) {
                    $rows[] = $row;
                }
            }

            return [
                'locale' => $locale,
                'dir' => Locales::isRtl($locale) ? 'rtl' : 'ltr',
                'store' => self::store(),
                'shop' => (string) $merchant->shop_name,
                'prices' => $prices,
                'category' => filled($category) ? $this->categories->findActiveBySlug((string) $category)?->name : null,
                'date' => Carbon::now()->format('Y-m-d'),
                'products' => $rows,
                'truncated' => $found->count() > $limit,
                'limit' => $limit,
            ];
        });
    }

    /**
     * What a share link shows: its merchant's catalog, in its category, language and prices.
     *
     * @return array<string, mixed>
     */
    public function forShare(CatalogShare $share): array
    {
        return $this->document($share->merchant, $share->category?->slug, $share->locale, $share->prices ?: self::RETAIL);
    }

    /**
     * A share link's PDF, drawn at most once every CACHE_SECONDS.
     */
    public function pdfForShare(CatalogShare $share): string
    {
        return $this->cached(self::shareCacheKey($share), fn () => $this->render($this->forShare($share)));
    }

    /**
     * A download's PDF, drawn at most once every CACHE_SECONDS for the same merchant, category,
     * language and prices.
     */
    public function pdfForDownload(Merchant $merchant, ?string $category, string $locale, string $prices): string
    {
        return $this->cached(self::downloadCacheKey($merchant, $category, $locale, $prices), fn () => $this->render($this->document($merchant, $category, $locale, $prices)));
    }

    public static function shareCacheKey(CatalogShare $share): string
    {
        return 'wholesale-catalog-pdf:share:'.$share->id;
    }

    /**
     * Per merchant, not only per tier: the PDF prints the shop's name.
     */
    public static function downloadCacheKey(Merchant $merchant, ?string $category, string $locale, string $prices): string
    {
        return 'wholesale-catalog-pdf:'.implode(':', [$merchant->id, (int) $merchant->tier_id, md5((string) $category), $locale, $prices]);
    }

    public static function forgetShare(CatalogShare $share): void
    {
        Cache::forget(self::shareCacheKey($share));
    }

    /**
     * The PDF itself (A4, portrait).
     *
     * @param  array<string, mixed>  $document  self::document()
     */
    public function render(array $document): string
    {
        return OrderDocument::inLocale($document['locale'], function () use ($document): string {
            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'default_font' => 'dejavusans',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 12,
                'img_dpi' => 96,
                'tempDir' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'mpdf', // font metrics only, no store data
            ]);

            $pdf->SetDirectionality($document['dir']);
            $pdf->SetTitle(($document['prices'] === self::WHOLESALE ? __('Wholesale catalog') : __('Catalog')).' — '.$document['shop']);
            $pdf->WriteHTML(view('wholesale::pdf.catalog', ['doc' => $document])->render());

            return $pdf->Output('', Destination::STRING_RETURN);
        });
    }

    /**
     * The cache table is text: the PDF is kept base64-encoded.
     *
     * @param  callable(): string  $draw
     */
    private function cached(string $key, callable $draw): string
    {
        return (string) base64_decode((string) Cache::remember($key, self::CACHE_SECONDS, fn () => base64_encode($draw())), true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function product(Product $product, PriceBook $book, bool $wholesale, int $budget, int &$used): ?array
    {
        $items = collect(WholesaleItems::of($product));
        $priced = $items->filter(fn (Product $item) => $book->base($item) !== null);

        if ($priced->isEmpty()) {
            return null;
        }

        $prices = $priced->map(fn (Product $item) => $book->base($item));
        $colors = $items
            ->map(fn (Product $item) => WholesaleItems::colorValue($item))
            ->filter()
            ->unique('id')
            ->sortBy(fn ($value) => [(int) $value->sort_order, (int) $value->id])
            ->map(fn ($value) => (string) $value->name)
            ->values()
            ->all();
        $sizes = $items
            ->filter(fn (Product $item) => WholesaleItems::sizeLabel($item) !== null)
            ->unique(fn (Product $item) => WholesaleItems::sizeLabel($item))
            ->sortBy(fn (Product $item) => WholesaleItems::sizeSort($item))
            ->map(fn (Product $item) => (string) WholesaleItems::sizeLabel($item))
            ->values()
            ->all();

        $image = self::thumbnail($product);

        if ($image !== null) {
            $size = (int) filesize($image);

            if ($used + $size > $budget) {
                $image = null;
            } else {
                $used += $size;
            }
        }

        return [
            'name' => (string) $product->name,
            'sku' => $product->sku,
            'image' => $image,
            'colors' => $colors,
            'sizes' => $sizes,
            'price_label' => $wholesale ? (new Price((float) $prices->min()))->format() : null,
            'price_from' => $wholesale && $prices->unique()->count() > 1,
            'retail_label' => (new Price((float) $priced->map(fn (Product $item) => $this->pricing->suggestedRetail($item)->amount)->min()))->format(),
            'breaks' => $wholesale ? array_map(
                fn (array $break) => __('From :count pieces: :percent% off', ['count' => $break['min_quantity'], 'percent' => rtrim(rtrim(number_format($break['discount_percent'], 2), '0'), '.')]),
                $book->breaksFor(PriceBook::groupId($product)),
            ) : [],
            'by_piece' => (bool) ($product->wholesale_by_piece ?? true),
        ];
    }

    /**
     * The product's main image as a small JPEG on this server (mPDF reads it from the disk; nothing
     * is fetched over the network), made once per image and kept; none when it cannot be made
     * within MAX_IMAGE_BYTES.
     */
    private static function thumbnail(Product $product): ?string
    {
        try {
            $media = $product->getFirstMedia('product');

            if ($media === null) {
                return null;
            }

            $path = storage_path('framework/cache/wholesale-catalog-thumbs/'.$media->id.'-'.($media->updated_at?->timestamp ?? 0).'.jpg');

            if (is_file($path)) {
                return $path;
            }

            $source = $media->getPath();
            $info = is_file($source) ? @getimagesize($source) : false;

            if ($info === false || $info[0] * $info[1] > self::MAX_SOURCE_PIXELS) {
                return null;
            }

            $image = match ($info[2]) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
                IMAGETYPE_PNG => @imagecreatefrompng($source),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
                IMAGETYPE_GIF => @imagecreatefromgif($source),
                default => false,
            };

            if ($image === false) {
                return null;
            }

            $width = min(self::THUMB_WIDTH, $info[0]);
            $height = max(1, (int) round($info[1] * $width / $info[0]));
            $thumb = imagecreatetruecolor($width, $height);
            // Transparent PNGs on white, as on paper.
            imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
            imagedestroy($image);

            File::ensureDirectoryExists(dirname($path));

            foreach ([75, 55] as $quality) {
                imagejpeg($thumb, $path, $quality);
                clearstatcache(true, $path);

                if (filesize($path) <= self::MAX_IMAGE_BYTES) {
                    imagedestroy($thumb);

                    return $path;
                }
            }

            imagedestroy($thumb);
            @unlink($path);

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{name: string, logo: ?string}
     */
    private static function store(): array
    {
        $logo = rescue(fn () => app(StoreAppearanceSettings::class)->logo_path, null, false);

        try {
            $logo = filled($logo) && Storage::disk('public')->exists($logo) ? Storage::disk('public')->path($logo) : null;
        } catch (Throwable) {
            $logo = null;
        }

        return ['name' => (string) app(GeneralSettings::class)->site_name, 'logo' => $logo];
    }
}
