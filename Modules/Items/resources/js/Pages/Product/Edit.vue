<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import CompleteTheLookCard from './parts/CompleteTheLookCard.vue';
import { toRequestBody, withUploadState } from './parts/productForm';
import type { ProductForm, ProductFormOptions, VariationRow } from './parts/types';
import ProductFields from './ProductFields.vue';

defineOptions({ layout: AdminLayout });

type SavedRow<T> = Omit<T, 'image' | 'gallery' | 'removed_image' | 'removed_gallery' | 'gallery_order' | 'gallery_colors' | 'gallery_new_colors'>;

const props = defineProps<
    ProductFormOptions & {
        /** "Complete the look", saved apart from the product's form. */
        look: Option[];
        product: Omit<SavedRow<ProductForm>, 'variations' | 'attributes'> & {
            id: number;
            variations: SavedRow<VariationRow>[];
            /** An empty PHP collection arrives as a list. */
            attributes: Record<string, number[]> | never[];
        };
    }
>();

const { id: productId, variations, attributes, ...values } = props.product;

/** The values a gallery image can show; a color deleted since the image was tagged is dropped. */
const colorIds = new Set(props.attributes.filter((attribute) => attribute.swatch).flatMap((attribute) => attribute.values.map((value) => value.id)));

const form = useForm<ProductForm>({
    ...withUploadState(values),
    variations: variations.map((row) => withUploadState(row)),
    attributes: Array.isArray(attributes) ? {} : attributes,
    gallery_colors: Object.fromEntries(values.gallery_images.map((image) => [String(image.id), image.color && colorIds.has(image.color) ? image.color : null])),
    gallery_new_colors: [],
});

function submit(): void {
    if (form.processing) return;

    form.transform((data) => toRequestBody(data as ProductForm, { _method: 'put' }) as never).post(route('products.update', productId), { forceFormData: true });
}
</script>

<template>
    <PageHeader :title="t('Edit Product')" :parents="[{ title: t('Products List'), href: route('products.index') }]" />
    <form @submit.prevent="submit">
        <ProductFields :form="form" :options="props" collapsed />
        <FormActions :processing="form.processing" :cancel-href="route('products.index')" />
    </form>
    <!-- Its own form: kept outside the product's. -->
    <CompleteTheLookCard :product-id="productId" :look="look" class="mt-4" />
</template>
