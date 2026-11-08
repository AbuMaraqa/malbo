<script setup lang="ts">
import { computed } from 'vue';

import SelectInput from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import { t } from '@/lib/i18n';

import type { ErrorOf, ProductRowFields } from './types';

defineProps<{ row: ProductRowFields; error: ErrorOf }>();

const stockStatuses = computed(() => [
    { value: 'in_stock', label: t('In Stock') },
    { value: 'out_of_stock', label: t('Out Of Stock') },
    { value: 'on_backorder', label: t('On Backorder') },
]);
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <Toggle v-model="row.manage_stock" :label="t('Manage Stock')" />
        <Toggle v-if="row.manage_stock" v-model="row.allow_backorder" :label="t('Allow Backorder')" />
        <TextInput v-if="row.manage_stock" v-model="row.stock_quantity" :label="t('Stock Quantity')" type="number" min="0" :error="error('stock_quantity')" />
        <TextInput v-if="row.manage_stock" v-model="row.low_stock_threshold" :label="t('Low Stock Threshold')" type="number" min="0" :error="error('low_stock_threshold')" />
        <SelectInput v-if="row.manage_stock" v-model="row.stock_status" :label="t('Stock Status')" :options="stockStatuses" :error="error('stock_status')" />
        <TextInput v-model="row.max_quantity_per_order" :label="t('Max Quantity Per Order')" type="number" min="1" :error="error('max_quantity_per_order')" />
    </div>
</template>
