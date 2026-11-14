<script setup lang="ts">
/**
 * The color each gallery image shows. On the storefront, choosing that color shows its images;
 * an image with no color always shows. Saved images are keyed by id, new files by their place in
 * `gallery`, which the request sends in the same order.
 */
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import { t } from '@/lib/i18n';

import type { ErrorOf, GalleryImage, ProductForm } from './types';

const props = defineProps<{ row: ProductForm; colors: Option[]; error: ErrorOf }>();

/** The saved images still kept, in the order the user dragged them to. */
const saved = computed<GalleryImage[]>(() => {
    const order = props.row.gallery_order;

    return props.row.gallery_images
        .filter((image) => !props.row.removed_gallery.includes(image.id))
        .sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
});

const files = computed<File[]>(() => (Array.isArray(props.row.gallery) ? props.row.gallery : props.row.gallery ? [props.row.gallery] : []));

/** The color picked for each new file, kept with the file itself so removing one moves none. */
const picked = reactive(new Map<File, number | null>());
const previews = ref(new Map<File, string>());

watch(
    files,
    (list) => {
        for (const [file, url] of previews.value) {
            if (!list.includes(file)) {
                URL.revokeObjectURL(url);
                previews.value.delete(file);
                picked.delete(file);
            }
        }
        for (const file of list) {
            if (!previews.value.has(file)) previews.value.set(file, URL.createObjectURL(file));
        }
    },
    { immediate: true },
);

watch(
    [files, picked],
    () => {
        // eslint-disable-next-line vue/no-mutating-props -- the form object is shared with the page on purpose
        props.row.gallery_new_colors = files.value.map((file) => picked.get(file) ?? null);
    },
    { immediate: true, deep: true },
);

onBeforeUnmount(() => previews.value.forEach((url) => URL.revokeObjectURL(url)));

function savedColor(id: number | string): number | null {
    return props.row.gallery_colors[String(id)] ?? null;
}

function setSaved(id: number | string, value: string | number | null | undefined): void {
    // eslint-disable-next-line vue/no-mutating-props -- the form object is shared with the page on purpose
    props.row.gallery_colors = { ...props.row.gallery_colors, [String(id)]: value === null || value === undefined || value === '' ? null : Number(value) };
}

function setNew(file: File, value: string | number | null | undefined): void {
    picked.set(file, value === null || value === undefined || value === '' ? null : Number(value));
}
</script>

<template>
    <fieldset v-if="colors.length && (saved.length || files.length)" class="grid gap-3">
        <legend class="mb-1 text-sm font-semibold">{{ t('Color of each gallery image') }}</legend>
        <p class="text-sm text-muted">{{ t('Choosing a color in the store shows its images. An image with no color always shows.') }}</p>
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="image in saved" :key="`saved-${image.id}`" class="flex items-center gap-3 rounded-control border border-line p-2">
                <img :src="image.url" alt="" class="size-14 shrink-0 rounded-control object-cover" />
                <SelectInput
                    class="min-w-0 flex-1"
                    :model-value="savedColor(image.id)"
                    :options="colors"
                    :label="t('Color')"
                    :placeholder="t('No color')"
                    :error="error(`gallery_colors.${image.id}`)"
                    @update:model-value="setSaved(image.id, $event)"
                />
            </li>
            <li v-for="(file, index) in files" :key="previews.get(file)" class="flex items-center gap-3 rounded-control border border-brand p-2">
                <img :src="previews.get(file)" alt="" class="size-14 shrink-0 rounded-control object-cover" />
                <SelectInput
                    class="min-w-0 flex-1"
                    :model-value="picked.get(file) ?? null"
                    :options="colors"
                    :label="t('Color')"
                    :placeholder="t('No color')"
                    :error="error(`gallery_new_colors.${index}`)"
                    @update:model-value="setNew(file, $event)"
                />
            </li>
        </ul>
    </fieldset>
</template>
