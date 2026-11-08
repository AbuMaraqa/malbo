<script setup lang="ts">
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import type { AppearanceForm, Localized } from './types';

/** The same text in Arabic and English, side by side. */
defineProps<{ form: AppearanceForm; name: string; label: string; textarea?: boolean; hint?: string }>();

const model = defineModel<Localized>({ required: true });
const errors = (form: AppearanceForm): Record<string, string | undefined> => form.errors as Record<string, string | undefined>;
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <template v-if="textarea">
            <TextareaInput v-model="model.ar" :label="`${label} (${t('Arabic')})`" :hint="hint" :maxlength="1000" :error="errors(form)[`${name}.ar`]" />
            <TextareaInput v-model="model.en" :label="`${label} (${t('English')})`" :maxlength="1000" :error="errors(form)[`${name}.en`]" />
        </template>
        <template v-else>
            <TextInput v-model="model.ar" :label="`${label} (${t('Arabic')})`" :hint="hint" :maxlength="160" :error="errors(form)[`${name}.ar`]" />
            <TextInput v-model="model.en" :label="`${label} (${t('English')})`" :maxlength="160" :error="errors(form)[`${name}.en`]" />
        </template>
    </div>
</template>
