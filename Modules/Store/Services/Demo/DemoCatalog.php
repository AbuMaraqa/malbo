<?php

namespace Modules\Store\Services\Demo;

/**
 * What the demo store sells: a men's fashion catalog in Arabic, English and Hebrew. Data only;
 * DemoStoreBuilder writes it through the services.
 */
final class DemoCatalog
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: ?string}> key => [ar, en, he, parent key] */
    public const CATEGORIES = [
        'men' => ['رجالي', 'Men', 'גברים', null],
        'shirts' => ['قمصان', 'Shirts', 'חולצות', 'men'],
        'tshirts' => ['تيشيرتات وبولو', 'T-shirts & polos', 'חולצות טי ופולו', 'men'],
        'trousers' => ['بناطيل', 'Trousers', 'מכנסיים', 'men'],
        'suits' => ['بدلات وبليزرات', 'Suits & blazers', 'חליפות ובלייזרים', 'men'],
        'jackets' => ['جاكيتات', 'Jackets', "ז'קטים", 'men'],
        'accessories' => ['إكسسوارات', 'Accessories', 'אביזרים', 'men'],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> key => [ar, en, he, hex] */
    public const COLORS = [
        'black' => ['أسود', 'Black', 'שחור', '#111111'],
        'navy' => ['كحلي', 'Navy', 'כחול כהה', '#1F2A44'],
        'white' => ['أبيض', 'White', 'לבן', '#FFFFFF'],
        'beige' => ['بيج', 'Beige', "בז'", '#D8C3A5'],
        'grey' => ['رمادي', 'Grey', 'אפור', '#8E8E8E'],
        'olive' => ['زيتي', 'Olive', 'זית', '#556B2F'],
        'sky' => ['سماوي', 'Light blue', 'תכלת', '#A7C7E7'],
        'burgundy' => ['خمري', 'Burgundy', 'בורדו', '#800020'],
        'brown' => ['بني', 'Brown', 'חום', '#6F4E37'],
    ];

    /** Sizes: letters for tops, waist for trousers, EU for suits. */
    public const SIZES = ['S', 'M', 'L', 'XL', 'XXL', '30', '32', '34', '36', '46', '48', '50', '52'];

    private const TOPS = ['S', 'M', 'L', 'XL', 'XXL'];

    private const WAIST = ['30', '32', '34', '36'];

    private const EU = ['46', '48', '50', '52'];

    /** @var array<string, array<string, array{0: string, 1: string, 2: string}>> preset => field => [ar, en, he] */
    public const MATERIALS = [
        'oxford' => [
            'fabric' => ['قطن أكسفورد منسوج', 'Woven Oxford cotton', 'כותנת אוקספורד ארוגה'],
            'composition' => ['100% قطن', '100% cotton', '100% כותנה'],
            'care' => ['غسيل آلي على 30 درجة، كي على حرارة متوسطة', 'Machine wash at 30°C, iron on medium heat', 'כביסה במכונה ב-30 מעלות, גיהוץ בחום בינוני'],
        ],
        'linen' => [
            'fabric' => ['كتان طبيعي خفيف', 'Light natural linen', 'פשתן טבעי קל'],
            'composition' => ['100% كتان', '100% linen', '100% פשתן'],
            'care' => ['غسيل يدوي أو آلي لطيف، لا تستخدم المجفف', 'Hand or gentle machine wash, do not tumble dry', 'כביסת יד או עדינה, ללא מייבש'],
        ],
        'stretch' => [
            'fabric' => ['قطن بوبلين مطاطي', 'Stretch cotton poplin', 'פופלין כותנה נמתח'],
            'composition' => ['97% قطن، 3% إيلاستين', '97% cotton, 3% elastane', '97% כותנה, 3% אלסטן'],
            'care' => ['غسيل آلي على 30 درجة', 'Machine wash at 30°C', 'כביסה במכונה ב-30 מעלות'],
        ],
        'flannel' => [
            'fabric' => ['فانيلا قطنية مربعات', 'Brushed cotton flannel check', 'פלנל כותנה משובץ'],
            'composition' => ['100% قطن', '100% cotton', '100% כותנה'],
            'care' => ['غسيل آلي على 40 درجة', 'Machine wash at 40°C', 'כביסה במכונה ב-40 מעלות'],
        ],
        'twill' => [
            'fabric' => ['قطن تويل', 'Cotton twill', 'טוויל כותנה'],
            'composition' => ['98% قطن، 2% إيلاستين', '98% cotton, 2% elastane', '98% כותנה, 2% אלסטן'],
            'care' => ['غسيل آلي على 30 درجة، يُقلب قبل الغسيل', 'Machine wash at 30°C inside out', 'כביסה במכונה ב-30 מעלות הפוך'],
        ],
        'wool' => [
            'fabric' => ['صوف ناعم', 'Fine wool', 'צמר עדין'],
            'composition' => ['70% صوف، 30% بوليستر', '70% wool, 30% polyester', '70% צמר, 30% פוליאסטר'],
            'care' => ['تنظيف جاف فقط', 'Dry clean only', 'ניקוי יבש בלבד'],
        ],
        'denim' => [
            'fabric' => ['دنيم مطاطي', 'Stretch denim', "ג'ינס נמתח"],
            'composition' => ['99% قطن، 1% إيلاستين', '99% cotton, 1% elastane', '99% כותנה, 1% אלסטן'],
            'care' => ['غسيل آلي بارد مع ألوان مشابهة', 'Machine wash cold with similar colours', 'כביסה קרה עם צבעים דומים'],
        ],
        'pique' => [
            'fabric' => ['قطن بيكيه', 'Cotton piqué', 'פיקה כותנה'],
            'composition' => ['100% قطن', '100% cotton', '100% כותנה'],
            'care' => ['غسيل آلي على 30 درجة', 'Machine wash at 30°C', 'כביסה במכונה ב-30 מעלות'],
        ],
        'leather' => [
            'fabric' => ['جلد طبيعي', 'Genuine leather', 'עור אמיתי'],
            'composition' => ['100% جلد بقري', '100% cowhide leather', '100% עור בקר'],
            'care' => ['يمسح بقطعة قماش جافة', 'Wipe with a dry cloth', 'לנגב במטלית יבשה'],
        ],
        'silk' => [
            'fabric' => ['حرير منسوج', 'Woven silk', 'משי ארוג'],
            'composition' => ['100% حرير', '100% silk', '100% משי'],
            'care' => ['تنظيف جاف فقط', 'Dry clean only', 'ניקוי יבש בלבד'],
        ],
        'nylon' => [
            'fabric' => ['نايلون مقاوم للرياح', 'Wind-resistant nylon', 'ניילון עמיד לרוח'],
            'composition' => ['100% بولي أميد، بطانة بوليستر', '100% polyamide, polyester lining', '100% פוליאמיד, בטנת פוליאסטר'],
            'care' => ['غسيل آلي لطيف على 30 درجة', 'Gentle machine wash at 30°C', 'כביסה עדינה ב-30 מעלות'],
        ],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string}> */
    public const FITS = [
        'regular' => ['قصّة عادية', 'Regular fit', 'גזרה רגילה'],
        'slim' => ['قصّة ضيقة', 'Slim fit', 'גזרה צרה'],
        'tailored' => ['قصّة مفصّلة', 'Tailored fit', 'גזרה מחויטת'],
        'relaxed' => ['قصّة مريحة', 'Relaxed fit', 'גזרה רפויה'],
    ];

    /**
     * The products. `stock` is what each item holds (null: an even spread of 0–20 per variant, some
     * low and some sold out). Prices are in the store's currency.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function products(): array
    {
        return [
            'oxford-shirt' => [
                'category' => 'shirts', 'names' => ['قميص أكسفورد كلاسيكي', 'Classic Oxford shirt', 'חולצת אוקספורד קלאסית'],
                'short' => ['قميص يومي أنيق بياقة بأزرار', 'An everyday button-down shirt', 'חולצה יומיומית עם צווארון מכופתר'],
                'price' => 149, 'sale' => null, 'wholesale' => 80, 'colors' => ['white', 'sky', 'navy'], 'sizes' => self::TOPS,
                'material' => 'oxford', 'fit' => 'regular', 'flags' => ['best'], 'model' => [182, 76, 'M'],
            ],
            'linen-shirt' => [
                'category' => 'shirts', 'names' => ['قميص كتان صيفي', 'Summer linen shirt', 'חולצת פשתן קיצית'],
                'short' => ['خفيف ومريح لأيام الصيف', 'Light and airy for summer days', 'קלה ונושמת לימי הקיץ'],
                'price' => 179, 'sale' => 139, 'wholesale' => 95, 'colors' => ['white', 'beige', 'olive'], 'sizes' => self::TOPS,
                'material' => 'linen', 'fit' => 'relaxed', 'flags' => ['new'], 'model' => [180, 74, 'L'],
            ],
            'stretch-shirt' => [
                'category' => 'shirts', 'names' => ['قميص سليم مطاطي', 'Slim stretch shirt', 'חולצה צרה נמתחת'],
                'short' => ['قصة ضيقة مع حرية في الحركة', 'A slim cut that moves with you', 'גזרה צרה שזזה איתך'],
                'price' => 159, 'sale' => null, 'wholesale' => 85, 'colors' => ['black', 'white'], 'sizes' => self::TOPS,
                'material' => 'stretch', 'fit' => 'slim', 'flags' => [], 'model' => null,
            ],
            'flannel-shirt' => [
                'category' => 'shirts', 'names' => ['قميص فانيلا مربعات', 'Flannel check shirt', 'חולצת פלנל משובצת'],
                'short' => ['دافئ وناعم لأيام الشتاء', 'Warm and soft for winter days', 'חמה ורכה לימי החורף'],
                'price' => 169, 'sale' => null, 'wholesale' => 90, 'colors' => ['burgundy', 'grey'], 'sizes' => self::TOPS,
                'material' => 'flannel', 'fit' => 'regular', 'flags' => [], 'model' => null,
            ],
            'polo' => [
                'category' => 'tshirts', 'names' => ['قميص بولو بيكيه', 'Piqué polo shirt', 'חולצת פולו פיקה'],
                'short' => ['بولو كلاسيكي بياقة مضلعة', 'A classic polo with a ribbed collar', 'פולו קלאסית עם צווארון מצולע'],
                'price' => 99, 'sale' => null, 'wholesale' => 50, 'colors' => ['navy', 'white', 'olive'], 'sizes' => ['S', 'M', 'L', 'XL'],
                'material' => 'pique', 'fit' => 'regular', 'flags' => ['best'], 'model' => [178, 72, 'M'],
            ],
            'crew-tee' => [
                'category' => 'tshirts', 'names' => ['تيشيرت قطني برقبة دائرية', 'Cotton crew-neck T-shirt', 'חולצת טי כותנה עם צווארון עגול'],
                'short' => ['الأساس في كل خزانة', 'The basic every wardrobe needs', 'הבסיס של כל ארון'],
                'price' => 69, 'sale' => 55, 'wholesale' => 32, 'colors' => ['black', 'white', 'grey'], 'sizes' => ['S', 'M', 'L', 'XL'],
                'material' => 'pique', 'fit' => 'regular', 'flags' => [], 'model' => null,
            ],
            'chinos' => [
                'category' => 'trousers', 'names' => ['بنطال تشينو', 'Chino trousers', "מכנסי צ'ינו"],
                'short' => ['تشينو مريح للعمل والخروج', 'Chinos for the office and the weekend', "צ'ינו למשרד ולסוף השבוע"],
                'price' => 189, 'sale' => null, 'wholesale' => 100, 'colors' => ['beige', 'navy', 'olive'], 'sizes' => self::WAIST,
                'material' => 'twill', 'fit' => 'slim', 'flags' => ['best'], 'model' => [182, 76, '32'],
            ],
            'wool-trousers' => [
                'category' => 'trousers', 'names' => ['بنطال صوف رسمي', 'Wool dress trousers', 'מכנסי צמר מחויטים'],
                'short' => ['بنطال رسمي بخصر مريح', 'Dress trousers with a comfortable waist', 'מכנסיים מחויטים עם מותן נוחה'],
                'price' => 249, 'sale' => null, 'wholesale' => 135, 'colors' => ['black', 'grey'], 'sizes' => self::WAIST,
                'material' => 'wool', 'fit' => 'tailored', 'flags' => [], 'model' => null,
            ],
            'slim-jeans' => [
                'category' => 'trousers', 'names' => ['جينز سليم', 'Slim jeans', "ג'ינס צר"],
                'short' => ['دنيم مطاطي بقصة ضيقة', 'Stretch denim in a slim cut', "ג'ינס נמתח בגזרה צרה"],
                'price' => 219, 'sale' => 179, 'wholesale' => 115, 'colors' => ['navy', 'black'], 'sizes' => self::WAIST,
                'material' => 'denim', 'fit' => 'slim', 'flags' => [], 'model' => [180, 75, '32'],
            ],
            'navy-suit' => [
                'category' => 'suits', 'names' => ['بدلة رسمية قطعتين', 'Two-piece suit', 'חליפה בת שני חלקים'],
                'short' => ['جاكيت وبنطال من الصوف الناعم', 'Jacket and trousers in fine wool', 'ז\'קט ומכנסיים מצמר עדין'],
                'price' => 899, 'sale' => null, 'wholesale' => 520, 'colors' => ['navy', 'black'], 'sizes' => self::EU,
                'material' => 'wool', 'fit' => 'tailored', 'flags' => ['featured'], 'model' => [185, 80, '50'],
            ],
            'linen-blazer' => [
                'category' => 'suits', 'names' => ['بليزر كتان', 'Linen blazer', 'בלייזר פשתן'],
                'short' => ['بليزر غير مبطن للصيف', 'An unlined summer blazer', 'בלייזר קיצי ללא בטנה'],
                'price' => 549, 'sale' => 449, 'wholesale' => 300, 'colors' => ['beige', 'sky'], 'sizes' => self::EU,
                'material' => 'linen', 'fit' => 'tailored', 'flags' => ['new'], 'model' => null,
            ],
            'bomber' => [
                'category' => 'jackets', 'names' => ['جاكيت بومبر', 'Bomber jacket', "ז'קט בומבר"],
                'short' => ['جاكيت خفيف بأساور مضلعة', 'A light jacket with ribbed cuffs', "ז'קט קל עם שרוולים מצולעים"],
                'price' => 399, 'sale' => null, 'wholesale' => 220, 'colors' => ['black', 'olive'], 'sizes' => ['S', 'M', 'L', 'XL'],
                'material' => 'nylon', 'fit' => 'regular', 'flags' => ['new'], 'model' => [183, 78, 'L'],
            ],
            'leather-belt' => [
                'category' => 'accessories', 'names' => ['حزام جلد', 'Leather belt', 'חגורת עור'],
                'short' => ['حزام جلد طبيعي بإبزيم معدني', 'Genuine leather with a metal buckle', 'עור אמיתי עם אבזם מתכת'],
                'price' => 119, 'sale' => null, 'wholesale' => 60, 'colors' => ['black', 'brown'], 'sizes' => [],
                'material' => 'leather', 'fit' => null, 'flags' => [], 'model' => null, 'stock' => 14,
            ],
            'silk-tie' => [
                'category' => 'accessories', 'names' => ['ربطة عنق حرير', 'Silk tie', 'עניבת משי'],
                'short' => ['ربطة عنق حرير منسوج', 'A woven silk tie', 'עניבת משי ארוגה'],
                'price' => 89, 'sale' => null, 'wholesale' => 45, 'colors' => [], 'sizes' => [],
                'material' => 'silk', 'fit' => null, 'flags' => [], 'model' => null, 'stock' => 25,
            ],
            'wool-scarf' => [
                'category' => 'accessories', 'names' => ['وشاح صوف', 'Wool scarf', 'צעיף צמר'],
                'short' => ['وشاح دافئ لأيام البرد', 'A warm scarf for cold days', 'צעיף חם לימים קרים'],
                'price' => 129, 'sale' => null, 'wholesale' => 65, 'colors' => [], 'sizes' => [],
                'material' => 'wool', 'fit' => null, 'flags' => [], 'model' => null, 'stock' => 0,
            ],
        ];
    }

    /**
     * An item's opening stock: an even spread over 0–20 for each colour and size, so the catalog
     * has items sold out, items running low (at or below 3) and well stocked ones.
     */
    public static function stockFor(string $key): int
    {
        return self::ORDERED[$key] ?? [12, 8, 0, 15, 3, 20, 6, 2, 10, 14, 1, 9, 18, 5, 11][crc32($key) % 15];
    }

    /** Items the demo orders take: enough stock for them (and some left). */
    private const ORDERED = [
        'oxford-shirt.white.S' => 20, 'oxford-shirt.white.M' => 25, 'oxford-shirt.white.L' => 22, 'oxford-shirt.white.XL' => 12,
        'oxford-shirt.sky.L' => 10, 'oxford-shirt.navy.XL' => 6,
        'chinos.beige.30' => 14, 'chinos.beige.32' => 20, 'chinos.beige.34' => 18, 'chinos.beige.36' => 9, 'chinos.navy.32' => 6,
        'polo.navy.M' => 12, 'polo.navy.L' => 15, 'slim-jeans.navy.32' => 8, 'crew-tee.black.M' => 10, 'linen-shirt.white.L' => 6,
        'bomber.black.L' => 5, 'bomber.olive.M' => 4, 'navy-suit.navy.50' => 4, 'linen-blazer.beige.48' => 4, 'flannel-shirt.grey.M' => 5,
    ];
}
