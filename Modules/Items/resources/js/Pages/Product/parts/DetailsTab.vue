<script setup lang="ts">
/**
 * Fabric, composition, fit, care and the model in the photos, of the product or of one variation.
 * A variation leaves a field empty to show the product's own on the storefront.
 */
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import type { ErrorOf, ProductRowFields } from './types';

defineProps<{ row: ProductRowFields; error: ErrorOf; inherits?: boolean }>();
</script>

<template>
    <div class="grid gap-4">
        <p v-if="inherits" class="text-sm text-muted">{{ t('Leave a field empty to show the product’s own value.') }}</p>
        <div class="grid gap-4 md:grid-cols-2">
            <TextareaInput v-model="row.fabric" :label="t('Fabric')" :rows="2" :error="error('fabric')" />
            <TextareaInput v-model="row.composition" :label="t('Composition')" :rows="2" :hint="t('For example: 95% cotton, 5% elastane')" :error="error('composition')" />
            <TextareaInput v-model="row.fit" :label="t('Fit')" :rows="2" :error="error('fit')" />
            <TextareaInput v-model="row.care" :label="t('Care')" :rows="2" :error="error('care')" />
        </div>
        <fieldset class="grid gap-4 md:grid-cols-3">
            <legend class="mb-2 text-sm font-semibold">{{ t('The model in the photos') }}</legend>
            <TextInput v-model="row.model_height" :label="t('Model height')" type="number" min="50" max="250" :hint="t('In centimetres')" :error="error('model_height')" />
            <TextInput v-model="row.model_weight" :label="t('Model weight')" type="number" min="20" max="300" :hint="t('In kilograms')" :error="error('model_weight')" />
            <TextInput v-model="row.model_size" :label="t('Size the model wears')" :maxlength="32" :error="error('model_size')" />
        </fieldset>
    </div>
</template>
