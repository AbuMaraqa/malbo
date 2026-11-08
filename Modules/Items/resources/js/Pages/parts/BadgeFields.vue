<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import ColorInput from '@/Components/form/ColorInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import ColorBadge from './ColorBadge.vue';

/** The form of a tag or a label: both are a colored badge with a name. */
export interface BadgeForm {
    name: string;
    slug: string;
    description: string;
    text_color: string;
    background_color: string;
}

defineProps<{ form: InertiaForm<BadgeForm> }>();
</script>

<template>
    <div class="grid gap-4">
        <TextInput v-model="form.name" :label="t('Name')" required :error="form.errors.name" />
        <TextInput v-model="form.slug" :label="t('Slug')" dir="ltr" :error="form.errors.slug" :hint="t('Leave empty to make it from the name')" />
        <TextareaInput v-model="form.description" :label="t('Description')" :error="form.errors.description" />
        <ColorInput v-model="form.text_color" :label="t('Text Color')" required :error="form.errors.text_color" />
        <ColorInput v-model="form.background_color" :label="t('Background Color')" required :error="form.errors.background_color" />
        <div v-if="form.name">
            <p class="mb-1 text-sm font-medium">{{ t('Preview') }}</p>
            <ColorBadge :text="form.name" :color="form.text_color" :background="form.background_color" />
        </div>
    </div>
</template>
