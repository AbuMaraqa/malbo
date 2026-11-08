import type { ProductForm, ProductRowFields, VariationRow } from './types';

/** An empty set of the fields a product and a variation share. */
export function emptyRow(): ProductRowFields {
    return {
        name: '',
        slug: '',
        short_description: '',
        description: '',
        sku: '',
        barcode: '',
        weight: '',
        is_featured: false,
        is_digital: false,
        base_price: '',
        sale_price: '',
        cost_price: '',
        wholesale_price: '',
        discount_type: 'none',
        discount_value: '',
        discount_start_date: '',
        discount_end_date: '',
        manage_stock: true,
        stock_quantity: '',
        low_stock_threshold: '',
        stock_status: 'in_stock',
        allow_backorder: false,
        max_quantity_per_order: '',
        category_id: [],
        meta_title: '',
        meta_description: '',
        meta_keywords: '',
        image: null,
        gallery: [],
        images: [],
        gallery_images: [],
        removed_image: [],
        removed_gallery: [],
        gallery_order: [],
    };
}

/** The saved row as a form row: the server sends no file fields or removal lists. */
export function withUploadState<T extends object>(row: T): T & Pick<ProductRowFields, 'image' | 'gallery' | 'removed_image' | 'removed_gallery' | 'gallery_order'> {
    return { ...row, image: null, gallery: [], removed_image: [], removed_gallery: [], gallery_order: [] };
}

function isFile(value: unknown): value is File {
    return typeof File !== 'undefined' && value instanceof File;
}

function files(value: File | File[] | null): File[] {
    return (Array.isArray(value) ? value : [value]).filter(isFile);
}

/**
 * The request body: everything but the files as one JSON field (see ProductRequest), the files under
 * their own form paths so a file's error comes back on its field.
 */
export function toRequestBody(data: ProductForm, extra: Record<string, unknown> = {}): Record<string, unknown> {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { image, gallery, images: _images, gallery_images: _galleryImages, attributes: _attributes, variations, ...rest } = data;
    const variationFiles: Record<number, { image?: File; gallery?: File[] }> = {};

    const rows = variations.map((row: VariationRow, index: number) => {
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        const { image: rowImage, gallery: rowGallery, images: _rowImages, gallery_images: _rowGalleryImages, ...fields } = row;
        const [first] = files(rowImage);
        const many = files(rowGallery);

        if (first || many.length) {
            variationFiles[index] = { ...(first ? { image: first } : {}), ...(many.length ? { gallery: many } : {}) };
        }

        return fields;
    });

    const [mainImage] = files(image);

    return {
        ...extra,
        payload: JSON.stringify({ ...rest, variations: rows }),
        ...(mainImage ? { image: mainImage } : {}),
        gallery: files(gallery),
        variations: variationFiles,
    };
}
