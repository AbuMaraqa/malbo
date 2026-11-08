import type { AttributeChoice, ProductForm, ProductRowFields, VariationRow } from './types';
import { emptyRow } from './productForm';

/** The parent's fields a variation copies. Never its name, slug, attributes or images. */
const INHERITED = [
    'short_description', 'description', 'barcode', 'weight', 'is_featured', 'is_digital',
    'base_price', 'sale_price', 'cost_price', 'wholesale_price',
    'discount_type', 'discount_value', 'discount_start_date', 'discount_end_date',
    'manage_stock', 'stock_quantity', 'low_stock_threshold', 'stock_status', 'allow_backorder',
    'category_id', 'meta_title', 'meta_description', 'meta_keywords',
] as const satisfies readonly (keyof ProductRowFields)[];

/** A copy, so a row and its parent never share one array. */
function inherited(parent: ProductRowFields): Partial<ProductRowFields> {
    const copy: Record<string, unknown> = {};

    for (const field of INHERITED) {
        const value = parent[field];
        copy[field] = Array.isArray(value) ? [...value] : value;
    }

    return copy as Partial<ProductRowFields>;
}

/** Every combination of the chosen values, one value per attribute. */
export function cartesian(lists: number[][]): number[][] {
    return lists.reduce<number[][]>((rows, values) => rows.flatMap((row) => values.map((value) => [...row, value])), [[]]);
}

/** Identifies a combination whatever order its values are in. */
export function variationKey(valueIds: number[]): string {
    return [...valueIds].sort((a, b) => a - b).join('-');
}

/** A URL slug for the Latin part of a name. Arabic stays empty: the server transliterates it. */
export function slugify(text: string): string {
    return text
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/** A slug from the browser only when the whole name is Latin; otherwise the server makes it. */
function slugOf(name: string): string {
    return /[^ -ɏ]/.test(name) ? '' : slugify(name);
}

function nameOf(parentName: string, valueIds: number[], names: Map<number, string>): string {
    return [parentName, ...valueIds.map((id) => names.get(id) ?? String(id))].filter(Boolean).join(' - ');
}

/**
 * The rows for the current selection. A combination that already has a row keeps it with all its
 * edits (and its id); a combination dropped earlier in this session comes back from `removed`; a new
 * one starts from the parent's data. The name always follows the parent; a slug the user
 * typed stays.
 */
export function generateVariations(form: ProductForm, attributes: AttributeChoice[], removed: Map<string, VariationRow>): VariationRow[] {
    const names = new Map(attributes.flatMap((attribute) => attribute.values.map((value) => [value.id, value.name] as const)));

    // Values in the attributes' order, whichever order they were ticked in.
    const lists = attributes
        .map((attribute) => {
            const chosen = form.attributes[attribute.id] ?? [];
            return attribute.values.map((value) => value.id).filter((id) => chosen.includes(id));
        })
        .filter((list) => list.length > 0);

    const current = new Map(form.variations.map((row) => [variationKey(row.attribute_values), row]));
    const combinations = lists.length ? cartesian(lists) : [];
    const keep = new Set(combinations.map(variationKey));

    current.forEach((row, key) => {
        if (!keep.has(key)) removed.set(key, row);
    });

    return combinations.map((valueIds) => {
        const key = variationKey(valueIds);
        const existing = current.get(key) ?? removed.get(key);
        const name = nameOf(form.name, valueIds, names);

        if (existing) {
            const typedSlug = existing.slug && existing.slug !== slugOf(existing.name);

            return { ...existing, name, slug: typedSlug ? existing.slug : slugOf(name), attribute_values: valueIds };
        }

        return { ...emptyRow(), ...inherited(form), id: null, attribute_values: valueIds, name, slug: slugOf(name) };
    });
}

/** Copies the parent's data into every row; each row keeps its name, slug, attributes and images. */
export function inheritParentData(form: ProductForm): VariationRow[] {
    return form.variations.map((row) => ({ ...row, ...inherited(form) }));
}
