import type { ExistingImage } from '@/Components/form/ImageUpload.vue';
import type { Option } from '@/Components/form/SelectInput.vue';

export interface BundleComponentRow {
    /** A saved component: sent back so it is updated in place (carts hold choices by it). */
    id?: number;
    product_id: number | string | null;
    quantity: number | string;
}

export interface BundleForm {
    bundle_type: string;
    name: string;
    short_description: string | null;
    description: string | null;
    slug: string | null;
    sku: string | null;
    status: number;
    is_active: boolean;
    is_new: boolean;
    is_featured: boolean;
    category_id: number[];
    /** `fixed`: the price typed; `percent`: a percentage off the components' regular prices. */
    pricing: 'fixed' | 'percent';
    base_price: number | string | null;
    sale_price: number | string | null;
    bundle_discount_percent: number | string | null;
    max_quantity_per_order: number | string | null;
    components: BundleComponentRow[];
    image: File | null;
    gallery: File[];
    removed_image: (number | string)[];
    removed_gallery: (number | string)[];
    gallery_order: (number | string)[];
}

/** The lists the form chooses from; the same props on Add and Edit. */
export interface BundleFormOptions {
    types: Option[];
    statuses: Option[];
    categories: Option[];
}

/** Saved images, shown on Edit only. */
export interface BundleImages {
    images: ExistingImage[];
    gallery_images: ExistingImage[];
}

export function emptyBundle(): BundleForm {
    return {
        bundle_type: 'set',
        name: '',
        short_description: '',
        description: '',
        slug: '',
        sku: '',
        status: 2,
        is_active: true,
        is_new: false,
        is_featured: false,
        category_id: [],
        pricing: 'fixed',
        base_price: '',
        sale_price: '',
        bundle_discount_percent: '',
        max_quantity_per_order: '',
        components: [{ product_id: null, quantity: 1 }],
        image: null,
        gallery: [],
        removed_image: [],
        removed_gallery: [],
        gallery_order: [],
    };
}
