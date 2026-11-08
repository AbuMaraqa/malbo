<script setup lang="ts">
/** The main image and the gallery, of the product or of one variation row. */
import { computed } from 'vue';

import ImageUpload from '@/Components/form/ImageUpload.vue';
import { t } from '@/lib/i18n';

import type { ErrorOf, ProductRowFields } from './types';

const props = defineProps<{ row: ProductRowFields; error: ErrorOf; imageRequired?: boolean }>();

/** The saved gallery in the order the user dragged it to. */
const galleryImages = computed(() => {
    const order = props.row.gallery_order;

    return [...props.row.gallery_images].sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
});

/** An error on any one file of the gallery shows under the gallery. */
const galleryError = computed(() => {
    for (let index = 0; index < 50; index++) {
        const message = props.error(`gallery.${index}`);
        if (message) return message;
    }

    return props.error('gallery');
});
</script>

<template>
    <div class="grid gap-6 md:grid-cols-2">
        <ImageUpload v-model="row.image" v-model:removed="row.removed_image" :existing="row.images" :label="t('Image')" :required="imageRequired" :error="error('image')" />
        <ImageUpload
            v-model="row.gallery"
            v-model:removed="row.removed_gallery"
            v-model:order="row.gallery_order"
            multiple
            reorderable
            :existing="galleryImages"
            :label="t('Gallery')"
            :error="galleryError"
        />
    </div>
</template>
