/**
 * The MALBO illustrations (public/brand/icons, from the brand guidelines) for a category or a
 * product without a picture, chosen from words in its name, in Arabic or English. Checked in this
 * order, so "jeans jacket" is a jacket and "sweatshirt" a hoodie.
 */
const icons: [string, string[]][] = [
    ['shoes', ['shoe', 'sneaker', 'boot', 'footwear', 'slipper', 'sandal', 'حذاء', 'احذيه', 'بوط', 'سنيكرز', 'شبشب', 'صندل']],
    ['jackets', ['jacket', 'coat', 'blazer', 'جاكيت', 'جاكت', 'جواكيت', 'معطف', 'بليزر', 'ستره']],
    ['hoodies', ['hoodie', 'sweatshirt', 'fleece', 'فوتر', 'هودي', 'سويت شيرت', 'سويتشيرت']],
    ['pants', ['pant', 'trouser', 'jean', 'short', 'jogger', 'بنطلون', 'بناطيل', 'بنطال', 'جينز', 'شورت', 'جوجر']],
    ['tees', ['t-shirt', 'tshirt', 'tee', 'shirt', 'top', 'polo', 'blouse', 'بلوز', 'بلايز', 'تيشيرت', 'تي شيرت', 'قميص', 'قمصان', 'بولو']],
    ['accessories', ['accessor', 'cap', 'hat', 'belt', 'watch', 'sock', 'bag', 'wallet', 'sunglass', 'اكسسوار', 'قبعه', 'كاب', 'حزام', 'احزمه', 'ساعه', 'ساعات', 'جوارب', 'شنط', 'حقيب', 'محفظ', 'نظار']],
    ['gifts', ['gift', 'هدايا', 'هديه']],
    ['offers', ['offer', 'sale', 'deal', 'عروض', 'خصم', 'تخفيض', 'تنزيلات']],
];

/** Lower case, and the Arabic letters people write either way made one. */
function normalize(text: string): string {
    return text.toLowerCase().replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي');
}

function matches(name: string, word: string): boolean {
    // Latin words start at a word boundary ("cap" is not in "escape"); Arabic ones may carry "ال" before them.
    return /^[a-z]/.test(word) ? new RegExp(`(^|[^a-z])${word}`).test(name) : name.includes(word);
}

export function brandIconFor(name: string): string | null {
    const text = normalize(name);
    const found = icons.find(([, words]) => words.some((word) => matches(text, word)));

    return found ? `/brand/icons/${found[0]}.svg` : null;
}
