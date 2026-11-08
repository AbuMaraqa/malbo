<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import ImageUpload, { type ExistingImage } from '@/Components/form/ImageUpload.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

export interface BrandForm {
    name: string;
    slug: string;
    description: string;
    image: File | File[] | null;
    removed_image: (number | string)[];
}

defineProps<{ form: InertiaForm<BrandForm>; images?: ExistingImage[]; imageRequired?: boolean }>();
</script>

<template>
    <div class="grid gap-4">
        <TextInput v-model="form.name" :label="t('Name')" required :error="form.errors.name" />
        <TextInput v-model="form.slug" :label="t('Slug')" dir="ltr" :error="form.errors.slug" :hint="t('Leave empty to make it from the name')" />
        <TextareaInput v-model="form.description" :label="t('Description')" :error="form.errors.description" />
        <ImageUpload v-model="form.image" v-model:removed="form.removed_image" :existing="images" :label="t('Image')" :required="imageRequired" :error="form.errors.image" />
    </div>
</template>
