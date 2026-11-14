<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { ExistingImage } from '@/Components/form/ImageUpload.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { ExternalLink } from '@/lib/icons';
import { route } from '@/lib/route';

import BundleFields from './BundleFields.vue';
import type { BundleForm, BundleFormOptions } from './parts/types';

defineOptions({ layout: AdminLayout });

type SavedBundle = Omit<BundleForm, 'image' | 'gallery' | 'removed_image' | 'removed_gallery' | 'gallery_order'> & {
    id: number;
    images: ExistingImage[];
    gallery_images: ExistingImage[];
};

const props = defineProps<BundleFormOptions & { bundle: SavedBundle; selectedComponents: Option[]; viewUrl: string }>();

const { id: bundleId, images, gallery_images: galleryImages, ...values } = props.bundle;

const form = useForm<BundleForm>({
    ...values,
    image: null,
    gallery: [],
    removed_image: [],
    removed_gallery: [],
    gallery_order: galleryImages.map((image) => image.id),
});

function submit(): void {
    if (form.processing) return;

    form.transform((data) => ({ ...data, _method: 'put' })).post(route('bundles.update', bundleId), { forceFormData: true });
}
</script>

<template>
    <PageHeader :title="t('Edit set or bundle')" :parents="[{ title: t('Sets and bundles'), href: route('bundles.index') }]">
        <template #actions>
            <a :href="viewUrl" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline dark:text-brand-400">
                <ExternalLink class="size-4" />
                {{ t('View in the store') }}
            </a>
        </template>
    </PageHeader>
    <form @submit.prevent="submit">
        <BundleFields :form="form" :options="props" :saved="{ images, gallery_images: galleryImages }" :selected-components="selectedComponents" />
        <FormActions :processing="form.processing" :cancel-href="route('bundles.index')" />
    </form>
</template>
