import type { ExistingImage } from '@/Components/form/ImageUpload.vue';
import type { Option } from '@/Components/form/SelectInput.vue';

export type ErrorOf = (field: string) => string | undefined;

/** What a product and a variation have alike. Numbers stay as typed: the server validates them. */
export interface ProductRowFields {
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    sku: string | null;
    barcode: string | null;
    weight: number | string | null;
    is_featured: boolean;
    is_digital: boolean;
    base_price: number | string | null;
    sale_price: number | string | null;
    cost_price: number | string | null;
    wholesale_price: number | string | null;
    discount_type: string | null;
    discount_value: number | string | null;
    discount_start_date: string | null;
    discount_end_date: string | null;
    manage_stock: boolean;
    stock_quantity: number | string | null;
    low_stock_threshold: number | string | null;
    stock_status: string | null;
    allow_backorder: boolean;
    max_quantity_per_order: number | string | null;
    category_id: (number | string)[];
    meta_title: string | null;
    meta_description: string | null;
    meta_keywords: string | null;
    /** New files. */
    image: File | File[] | null;
    gallery: File | File[] | null;
    /** Saved images, shown only: the server ignores them. */
    images: ExistingImage[];
    gallery_images: ExistingImage[];
    removed_image: (number | string)[];
    removed_gallery: (number | string)[];
    gallery_order: (number | string)[];
}

export interface VariationRow extends ProductRowFields {
    id: number | null;
    attribute_values: number[];
}

export interface AllowedOptionRow {
    addon_option_id: number | string | null;
    price: number | string | null;
    is_default: boolean;
}

export interface AddonRow {
    addon_id: number | string | null;
    is_required: boolean;
    min_selection: number | string | null;
    max_selection: number | string | null;
    max_quantity_per_option: number | string | null;
    allowed_options: AllowedOptionRow[];
}

export interface ProductForm extends ProductRowFields {
    status: number;
    brand_id: number | string | null;
    tags: string[];
    labels: string[];
    branches: (number | string)[];
    addons: AddonRow[];
    variations: VariationRow[];
    /** attribute id => chosen value ids. Browser only: it generates the rows. */
    attributes: Record<string, number[]>;
}

export interface AttributeChoice {
    id: number;
    name: string;
    values: { id: number; name: string; color: string | null }[];
}

export interface AddonChoice {
    id: number;
    name: string;
    options: { id: number; name: string; price: number | string }[];
}

/** The lists the form chooses from; the same props on Add and Edit. */
export interface ProductFormOptions {
    statuses: Option[];
    categories: Option[];
    brands: Option[];
    branches: Option[];
    tagSuggestions: string[];
    labelSuggestions: string[];
    attributes: AttributeChoice[];
    addons: AddonChoice[];
}
