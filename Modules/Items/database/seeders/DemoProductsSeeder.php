<?php

namespace Modules\Items\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Modules\Core\database\seeders\AdminSeeder;
use Modules\Core\Entities\User;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Services\CategoryService;
use Modules\Items\Services\ProductService;

/**
 * Demo catalog for trying the store: 10 published products in every category, in Arabic and
 * English, through the same services the admin uses (stock enters the inventory ledger). A store
 * without categories first gets MALBO's six (brand guidelines). Products have no pictures, so the
 * storefront shows the MALBO illustration of their kind.
 *
 * Not part of `db:seed`. Run it on purpose:
 *   php artisan db:seed --class="Modules\Items\database\seeders\DemoProductsSeeder"
 * Safe to run again: a demo product is known by its slug (`demo-c{category}-{n}`) and never made twice.
 */
class DemoProductsSeeder extends Seeder
{
    public const PER_CATEGORY = 10;

    /** The categories a store without any gets: slug => [ar, en]. */
    private const CATEGORIES = [
        'shoes' => ['الأحذية', 'Shoes'],
        'jackets' => ['جاكيتات', 'Jackets'],
        'hoodies' => ['فوتر', 'Hoodies'],
        'pants' => ['بناطيل', 'Pants'],
        'tops' => ['بلايز', 'Tops'],
        'accessories' => ['الإكسسوارات', 'Accessories'],
    ];

    /** Words in a category's name that tell its kind (Arabic letters normalized, see kindOf()). */
    private const KIND_WORDS = [
        'shoes' => ['shoe', 'sneaker', 'boot', 'footwear', 'حذاء', 'احذيه', 'بوط', 'سنيكرز'],
        'jackets' => ['jacket', 'coat', 'جاكيت', 'جاكت', 'جواكيت', 'معطف'],
        'hoodies' => ['hoodie', 'sweat', 'فوتر', 'هودي'],
        'pants' => ['pant', 'trouser', 'jean', 'short', 'بنطلون', 'بناطيل', 'بنطال', 'جينز', 'شورت'],
        'tops' => ['shirt', 'tee', 'top', 'polo', 'بلوز', 'بلايز', 'تيشيرت', 'قميص', 'قمصان'],
        'accessories' => ['accessor', 'cap', 'hat', 'bag', 'اكسسوار', 'قبعه', 'كاب', 'شنط'],
    ];

    /** Ten pieces per kind: [ar, en, price ₪]. */
    private const PIECES = [
        'shoes' => [
            ['حذاء مشي خفيف', 'Lightweight Walking Shoe', 289], ['حذاء جري مبطّن', 'Cushioned Running Shoe', 349],
            ['سنيكرز جلد كلاسيكي', 'Classic Leather Sneakers', 319], ['حذاء تمارين شبكي', 'Mesh Training Shoe', 259],
            ['سنيكرز بنعل عالٍ', 'Chunky Sole Sneakers', 379], ['حذاء كاجوال سويدي', 'Suede Casual Shoe', 299],
            ['حذاء مشي للمسافات', 'Trail Walking Shoe', 399], ['سنيكرز قماش', 'Canvas Sneakers', 189],
            ['حذاء سليب أون', 'Slip-On Shoe', 219], ['حذاء كرة سلة عالٍ', 'High-Top Basketball Shoe', 429],
        ],
        'jackets' => [
            ['جاكيت بومبر', 'Bomber Jacket', 329], ['جاكيت رياضي بسحّاب', 'Track Jacket', 249],
            ['جاكيت جينز', 'Denim Jacket', 289], ['جاكيت منفوخ', 'Puffer Jacket', 449],
            ['جاكيت قطيفة', 'Corduroy Jacket', 269], ['جاكيت واقٍ من الريح', 'Windbreaker', 229],
            ['جاكيت فارسيتي', 'Varsity Jacket', 379], ['جاكيت فليس', 'Fleece Jacket', 239],
            ['باركا خفيفة', 'Light Parka', 499], ['جاكيت يوتيليتي', 'Utility Jacket', 349],
        ],
        'hoodies' => [
            ['هودي كلاسيكي', 'Classic Hoodie', 189], ['هودي واسع', 'Oversized Hoodie', 219],
            ['هودي بسحّاب', 'Zip-Up Hoodie', 229], ['سويت شيرت برقبة دائرية', 'Crewneck Sweatshirt', 169],
            ['سويت شيرت بنصف سحّاب', 'Half-Zip Sweatshirt', 199], ['هودي بخطوط جانبية', 'Piped Hoodie', 239],
            ['هودي ثقيل', 'Heavyweight Hoodie', 259], ['سويت شيرت فليس', 'Fleece Sweatshirt', 179],
            ['هودي مطبوع', 'Graphic Hoodie', 209], ['هودي قصير', 'Cropped Hoodie', 189],
        ],
        'pants' => [
            ['بنطلون كارغو', 'Cargo Pants', 229], ['بنطلون رياضي', 'Track Pants', 179],
            ['بنطلون تشينو ضيّق', 'Slim Chinos', 199], ['جينز مستقيم', 'Straight Jeans', 249],
            ['بنطلون جوجر', 'Joggers', 169], ['بنطلون واسع', 'Wide-Leg Pants', 219],
            ['بنطلون فوتر', 'Sweatpants', 159], ['شورت جينز', 'Denim Shorts', 149],
            ['شورت تمارين', 'Training Shorts', 119], ['بنطلون كتان', 'Linen Pants', 209],
        ],
        'tops' => [
            ['تيشيرت أساسي', 'Basic T-Shirt', 79], ['تيشيرت واسع', 'Oversized T-Shirt', 99],
            ['قميص بولو', 'Polo Shirt', 139], ['تيشيرت بأكمام طويلة', 'Long-Sleeve Tee', 109],
            ['بلوزة هنلي', 'Henley Top', 119], ['تيشيرت رياضي', 'Sports Tee', 89],
            ['تيشيرت مخطّط', 'Striped Tee', 99], ['قميص أكسفورد', 'Oxford Shirt', 179],
            ['قميص أوفرشيرت', 'Overshirt', 189], ['تيشيرت مطبوع', 'Graphic Tee', 109],
        ],
        'accessories' => [
            ['كاب بيسبول', 'Baseball Cap', 89], ['قبعة صوف', 'Beanie', 69],
            ['جوارب رياضية (3 أزواج)', 'Sport Socks (3 pairs)', 49], ['حزام قماش', 'Canvas Belt', 79],
            ['محفظة بطاقات', 'Card Holder', 99], ['حقيبة برباط', 'Drawstring Bag', 59],
            ['نظارة شمسية', 'Sunglasses', 149], ['سوار مجدول', 'Braided Bracelet', 69],
            ['ساعة يد', 'Wristwatch', 299], ['حقيبة توت', 'Tote Bag', 99],
        ],
    ];

    /** The brand's palette first: [ar, en]. */
    private const COLORS = [['عنابي', 'Maroon'], ['كريمي', 'Cream'], ['أسود', 'Black'], ['بيج', 'Beige'], ['كحلي', 'Navy'], ['زيتي', 'Olive']];

    /** [ar, en] lines under each piece, per kind. */
    private const LINES = [
        'shoes' => ['خفيف ومريح لكل خطوة، بنعل يمتص الصدمات.', 'Light and comfortable for every step, with a cushioned sole.'],
        'jackets' => ['قصّة عصرية تحميك من البرد وتكمل إطلالتك.', 'A modern cut that keeps you warm and finishes your look.'],
        'hoodies' => ['قماش فوتر ناعم ودافئ، لأيامك العادية.', 'Soft, warm fleece for your everyday.'],
        'pants' => ['قصّة مريحة تتحرك معك طوال اليوم.', 'A comfortable fit that moves with you all day.'],
        'tops' => ['قطن ناعم يناسب كل يوم.', 'Soft cotton for every day.'],
        'accessories' => ['التفصيلة التي تكمل إطلالتك.', 'The detail that completes your look.'],
    ];

    public function run(): void
    {
        $userId = $this->userId();
        $categories = Category::query()->with('translations')->orderBy('id')->get();

        if ($categories->isEmpty()) {
            $categories = $this->createCategories($userId);
        }

        $made = 0;
        foreach ($categories as $category) {
            $made += $this->fill($category, $userId);
        }

        $this->command?->info("Demo products: {$made} added across {$categories->count()} categories.");
    }

    private function fill(Category $category, int $userId): int
    {
        $kind = self::kindOf((string) $category->translate('ar')?->name.' '.$category->translate('en')?->name.' '.$category->slug);
        $made = 0;

        for ($n = 1; $n <= self::PER_CATEGORY; $n++) {
            $slug = "demo-c{$category->id}-{$n}";

            if (Product::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            $this->make($category, $kind, $n, $slug, $userId);
            $made++;
        }

        return $made;
    }

    private function make(Category $category, ?string $kind, int $n, string $slug, int $userId): void
    {
        // A category of no known kind gets a mix: one kind after another.
        $kinds = array_keys(self::PIECES);
        $pieceKind = $kind ?? $kinds[($n - 1) % count($kinds)];
        [$ar, $en, $price] = self::PIECES[$pieceKind][$n - 1];
        [$colorAr, $colorEn] = self::COLORS[($n - 1 + $category->id) % count(self::COLORS)];
        [$lineAr, $lineEn] = self::LINES[$pieceKind];

        $product = app(ProductService::class)->create([
            'slug' => $slug,
            'name' => "{$ar} - {$colorAr}",
            'short_description' => $lineAr,
            'description' => "<p>{$lineAr}</p><p>من مجموعة MALBO: جودة وراحة وتصميم عصري بسعر يناسبك.</p>",
            'base_price' => $price,
            // Every third piece is on offer, 20% off rounded to a whole shekel.
            'sale_price' => $n % 3 === 0 ? round($price * 0.8) : null,
            'manage_stock' => 1,
            // The last piece runs low, to show the low-stock badge.
            'stock_quantity' => $n === self::PER_CATEGORY ? 3 : 10 + ($n * 7) % 31,
            'status' => ProductStatus::PUBLISHED->value,
            'is_active' => ProductActive::IS_ACTIVE->value,
            'is_featured' => $n <= 2 ? 1 : 0,
            'is_new' => $n <= 3 ? 1 : 0,
            'is_best_seller' => $n === 4 ? 1 : 0,
            'category_id' => [$category->id],
        ], 'ar', $userId);

        Translations::fill($product, ['en' => [
            'name' => "{$en} - {$colorEn}",
            'short_description' => $lineEn,
            'description' => "<p>{$lineEn}</p><p>From the MALBO collection: quality, comfort and modern design at a fair price.</p>",
        ]]);
        $product->save();
    }

    /**
     * @return Collection<int, Category>
     */
    private function createCategories(int $userId): Collection
    {
        $service = app(CategoryService::class);
        $order = 0;

        return collect(self::CATEGORIES)->map(fn (array $names, string $slug) => $service->create(
            ['slug' => $slug, 'status' => CategoryStatus::ACTIVE->value, 'sort_order' => $order++],
            ['ar' => ['name' => $names[0]], 'en' => ['name' => $names[1]]],
            null,
            $userId,
        ))->values();
    }

    private function userId(): int
    {
        if (! User::query()->exists()) {
            $this->call(AdminSeeder::class);
        }

        return (int) (User::query()->where('email', config('admin.email'))->value('id') ?? User::query()->min('id'));
    }

    /** The kind a category's name points to (shoes, jackets…), or null. */
    public static function kindOf(string $name): ?string
    {
        $text = str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], mb_strtolower($name));

        foreach (self::KIND_WORDS as $kind => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    return $kind;
                }
            }
        }

        return null;
    }
}
