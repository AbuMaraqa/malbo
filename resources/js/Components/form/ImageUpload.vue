<script setup lang="ts">
import { ImagePlus, X } from '@/lib/icons';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { t } from '@/lib/i18n';

import Field from './Field.vue';

export interface ExistingImage {
    id: number | string;
    url: string;
}

const props = withDefaults(
    defineProps<{
        label?: string;
        error?: string;
        hint?: string;
        required?: boolean;
        multiple?: boolean;
        /** Images already saved. The ids the user removes land in `removed`. */
        existing?: ExistingImage[];
        accept?: string;
        /** Saved images can be dragged; their ids land in `order` (`existing` is yours to sort by it). */
        reorderable?: boolean;
    }>(),
    { existing: () => [], accept: 'image/jpeg,image/png,image/webp,image/gif' },
);

/** New files: one File, or File[] when `multiple`. */
const model = defineModel<File | File[] | null>();
const removed = defineModel<(number | string)[]>('removed', { default: () => [] });

const order = defineModel<(number | string)[]>('order', { default: () => [] });

const previews = ref<{ file: File; url: string }[]>([]);
const list = ref<HTMLElement>();
const input = ref<HTMLInputElement>();

const kept = computed(() => props.existing.filter((image) => !removed.value.includes(image.id)));
const showAdd = computed(() => props.multiple || (kept.value.length === 0 && previews.value.length === 0));

function revoke(): void {
    previews.value.forEach((preview) => URL.revokeObjectURL(preview.url));
}

watch(
    model,
    (value) => {
        revoke();
        const files = Array.isArray(value) ? value : value ? [value] : [];
        previews.value = files.map((file) => ({ file, url: URL.createObjectURL(file) }));
    },
    { immediate: false },
);

let sortable: { destroy: () => void } | null = null;

onMounted(async () => {
    if (!props.reorderable || !list.value) return;

    const { default: Sortable } = await import('sortablejs');

    if (!list.value) return;

    sortable = Sortable.create(list.value, {
        draggable: '[data-saved-image]',
        animation: 150,
        onEnd: ({ item, from, oldDraggableIndex: oldIndex, newDraggableIndex: newIndex }) => {
            if (oldIndex === undefined || newIndex === undefined || oldIndex === newIndex) return;

            // Vue owns these nodes: put the image back and let the new order re-render the list.
            from.removeChild(item);
            from.insertBefore(item, from.children[oldIndex] ?? null);

            const ids = kept.value.map((image) => image.id);
            ids.splice(newIndex, 0, ...ids.splice(oldIndex, 1));
            order.value = ids;
        },
    });
});

onBeforeUnmount(() => {
    revoke();
    sortable?.destroy();
});

function picked(event: Event): void {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);
    model.value = props.multiple ? [...(Array.isArray(model.value) ? model.value : []), ...files] : (files[0] ?? null);
    if (input.value) input.value.value = '';
}

function removeNew(file: File): void {
    model.value = Array.isArray(model.value) ? model.value.filter((item) => item !== file) : null;
}

function removeExisting(id: number | string): void {
    removed.value = [...removed.value, id];
}
</script>

<template>
    <Field :label="label" :error="error" :hint="hint" :required="required">
        <div ref="list" class="flex flex-wrap gap-2">
            <div v-for="image in kept" :key="image.id" data-saved-image class="relative size-24 overflow-hidden rounded-control border border-line" :class="{ 'cursor-grab': reorderable }">
                <img :src="image.url" alt="" class="size-full object-cover" />
                <button type="button" class="absolute end-1 top-1 rounded bg-card/90 p-0.5 text-ink" :aria-label="t('Remove')" @click="removeExisting(image.id)"><X class="size-3.5" /></button>
            </div>
            <div v-for="preview in previews" :key="preview.url" class="relative size-24 overflow-hidden rounded-control border border-brand">
                <img :src="preview.url" alt="" class="size-full object-cover" />
                <button type="button" class="absolute end-1 top-1 rounded bg-card/90 p-0.5 text-ink" :aria-label="t('Remove')" @click="removeNew(preview.file)"><X class="size-3.5" /></button>
            </div>
            <label
                v-if="showAdd"
                class="flex size-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-control border border-dashed border-line text-xs text-muted hover:border-brand hover:text-ink"
            >
                <ImagePlus class="size-5" />
                {{ t('Add image') }}
                <input ref="input" type="file" class="sr-only" :accept="accept" :multiple="multiple" @change="picked" />
            </label>
        </div>
    </Field>
</template>
