<script setup lang="ts">
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import type { ErrorOf, ProductRowFields } from './types';

defineProps<{ row: ProductRowFields; error: ErrorOf }>();
</script>

<template>
    <div class="grid gap-4">
        <TextInput v-model="row.meta_title" :label="t('Meta Title')" :maxlength="60" :error="error('meta_title')" />
        <TextareaInput v-model="row.meta_description" :label="t('Meta Description')" :maxlength="160" :error="error('meta_description')" />
        <TextInput v-model="row.meta_keywords" :label="t('Meta Keywords')" :error="error('meta_keywords')" />
    </div>
</template>
