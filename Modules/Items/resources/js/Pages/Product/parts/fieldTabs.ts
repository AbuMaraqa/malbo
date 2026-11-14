/**
 * Which tab shows each field, so a validation error can mark its tab and open it. A variation row has
 * its own tabs, over the same fields.
 */
const GROUPS: Record<string, readonly string[]> = {
    general: ['sku', 'barcode', 'weight', 'is_featured', 'is_digital', 'is_new', 'is_best_seller', 'status'],
    details: ['fabric', 'composition', 'fit', 'care', 'model_height', 'model_weight', 'model_size'],
    price: ['base_price', 'sale_price', 'cost_price', 'wholesale_price', 'discount_type', 'discount_value', 'discount_start_date', 'discount_end_date'],
    inventory: ['manage_stock', 'stock_quantity', 'low_stock_threshold', 'stock_status', 'allow_backorder', 'max_quantity_per_order'],
    addons: ['addons'],
    variations: ['variations', 'attributes'],
    images: ['image', 'gallery', 'removed_image', 'removed_gallery', 'gallery_order', 'gallery_colors', 'gallery_new_colors', 'video_url'],
    categories: ['category_id'],
    seo: ['meta_title', 'meta_description', 'meta_keywords'],
};

/** The first segment of `variations.3.base_price` is what decides. */
function matches(key: string, fields: readonly string[]): boolean {
    return fields.includes(key.split('.')[0]);
}

/** Error keys that belong to a tab. */
export function tabsWithError(keys: string[]): Set<string> {
    const tabs = new Set<string>();

    for (const key of keys) {
        for (const [tab, fields] of Object.entries(GROUPS)) {
            if (matches(key, fields)) tabs.add(tab);
        }
    }

    return tabs;
}

/** The tab of a variation row that holds a field: its error keys, without the `variations.3.` prefix. */
export function rowTabsWithError(errors: Record<string, string | undefined>, prefix: string): Set<string> {
    return tabsWithError(
        Object.keys(errors)
            .filter((key) => key.startsWith(prefix) && errors[key])
            .map((key) => key.slice(prefix.length)),
    );
}
