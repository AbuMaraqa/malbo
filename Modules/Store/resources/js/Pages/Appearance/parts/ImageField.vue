<script setup lang="ts">
import { computed } from 'vue';

import ImageUpload from '@/Components/form/ImageUpload.vue';

import type { AppearanceForm, ImageField, SavedImages } from './types';

/** One image of the store: the saved one with a way to remove it, or a new file. */
const props = defineProps<{ form: AppearanceForm; field: ImageField; label: string; images: SavedImages; accept?: string; hint?: string }>();

const existing = computed(() => (props.images[props.field] ? [{ id: props.field, url: props.images[props.field] as string }] : []));
</script>

<template>
    <ImageUpload
        v-model="form[field] as File | null"
        v-model:removed="form.removed_images as (string | number)[]"
        :label="label"
        :existing="existing"
        :accept="accept"
        :hint="hint"
        :error="form.errors[field]"
    />
</template>
