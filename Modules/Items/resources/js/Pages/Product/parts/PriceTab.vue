<script setup lang="ts">
import { computed } from 'vue';

import SelectInput from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import type { ErrorOf, ProductRowFields } from './types';

const props = defineProps<{ row: ProductRowFields; error: ErrorOf }>();

const discountTypes = computed(() => [
    { value: 'none', label: t('No discount') },
    { value: 'percentage', label: t('Percentage') },
    { value: 'fixed', label: t('Fixed') },
]);

const hasDiscount = computed(() => props.row.discount_type === 'percentage' || props.row.discount_type === 'fixed');
</script>

<template>
    <div class="grid gap-6 md:grid-cols-2">
        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-2 text-sm font-semibold">{{ t('Price') }}</legend>
            <TextInput v-model="row.base_price" :label="t('Base Price')" type="number" step="0.01" min="0" required :error="error('base_price')" />
            <TextInput v-model="row.sale_price" :label="t('Sale Price')" type="number" step="0.01" min="0" :error="error('sale_price')" />
            <TextInput v-model="row.cost_price" :label="t('Cost Price')" type="number" step="0.01" min="0" :error="error('cost_price')" />
            <TextInput v-model="row.wholesale_price" :label="t('Wholesale Price')" type="number" step="0.01" min="0" :error="error('wholesale_price')" />
        </fieldset>
        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-2 text-sm font-semibold">{{ t('Discount') }}</legend>
            <SelectInput v-model="row.discount_type" :label="t('Discount Type')" :options="discountTypes" :error="error('discount_type')" />
            <TextInput v-if="hasDiscount" v-model="row.discount_value" :label="t('Discount Amount')" type="number" step="0.01" min="0" :error="error('discount_value')" />
            <TextInput v-if="hasDiscount" v-model="row.discount_start_date" :label="t('Discount Start Date')" type="date" :error="error('discount_start_date')" />
            <TextInput v-if="hasDiscount" v-model="row.discount_end_date" :label="t('Discount End Date')" type="date" :error="error('discount_end_date')" />
        </fieldset>
    </div>
</template>
