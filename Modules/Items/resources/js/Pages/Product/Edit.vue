<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import { toRequestBody, withUploadState } from './parts/productForm';
import type { ProductForm, ProductFormOptions, VariationRow } from './parts/types';
import ProductFields from './ProductFields.vue';

defineOptions({ layout: AdminLayout });

type SavedRow<T> = Omit<T, 'image' | 'gallery' | 'removed_image' | 'removed_gallery' | 'gallery_order'>;

const props = defineProps<
    ProductFormOptions & {
        product: Omit<SavedRow<ProductForm>, 'variations' | 'attributes'> & {
            id: number;
            variations: SavedRow<VariationRow>[];
            /** An empty PHP collection arrives as a list. */
            attributes: Record<string, number[]> | never[];
        };
    }
>();

const { id: productId, variations, attributes, ...values } = props.product;

const form = useForm<ProductForm>({
    ...withUploadState(values),
    variations: variations.map((row) => withUploadState(row)),
    attributes: Array.isArray(attributes) ? {} : attributes,
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
</template>
