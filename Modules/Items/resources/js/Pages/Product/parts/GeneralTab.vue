<script setup lang="ts">
/** SKU, barcode, weight and flags, for the product and for a variation row (which has no status). */
import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import { t } from '@/lib/i18n';

import type { ErrorOf, ProductRowFields } from './types';

defineProps<{ row: ProductRowFields & { status?: number }; error: ErrorOf; statuses?: Option[] }>();
</script>

<template>
    <div class="grid gap-4 md:grid-cols-3">
        <TextInput v-model="row.sku" :label="t('Sku')" dir="ltr" :placeholder="t('Keep empty for auto generated')" :error="error('sku')" />
        <TextInput v-model="row.barcode" :label="t('Barcode')" dir="ltr" :error="error('barcode')" />
        <TextInput v-model="row.weight" :label="t('Weight')" type="number" step="0.01" min="0" :error="error('weight')" />
        <Toggle v-model="row.is_featured" :label="t('Is this product featured')" />
        <Toggle v-model="row.is_digital" :label="t('Is this product digital')" />
        <SelectInput v-if="statuses" v-model="row.status" :label="t('Status')" required :options="statuses" :error="error('status')" />
    </div>
</template>
