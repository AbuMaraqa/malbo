<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    name: string;
    sku: string | null;
    stock: number;
    threshold: number;
    backorder: boolean;
    edit_product_id: number;
}

const props = defineProps<{ table: TablePayload<Row>; categories: Option[] }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Product') },
    { key: 'sku', label: t('SKU') },
    { key: 'stock', label: t('Stock'), sort: 'stock_quantity', class: 'tabular' },
    { key: 'threshold', label: t('Low stock threshold'), sort: 'low_stock_threshold', class: 'tabular', toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { key: 'category', label: t('Category'), options: props.categories },
    { type: 'boolean', key: 'out_of_stock', label: t('Out of stock'), trueLabel: t('Out of stock only'), falseLabel: t('Still in stock') },
]);
</script>

<template>
    <PageHeader :title="t('Low stock')" :count="table.meta.total" :description="t('Products and variants at or below their low-stock threshold, the emptiest first.')" />

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('Nothing runs low')" :empty-text="t('Every product with managed stock is above its threshold.')">
        <template #cell-name="{ row }">
            <Link v-if="can('Product Update')" :href="route('products.edit', row.edit_product_id)" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ row.name }}</Link>
            <span v-else>{{ row.name }}</span>
        </template>
        <template #cell-sku="{ row }">
            <span dir="ltr">{{ row.sku }}</span>
        </template>
        <template #cell-stock="{ row }">
            <Badge :tone="row.stock <= 0 ? 'danger' : 'warning'">{{ row.stock }}</Badge>
        </template>
        <template #actions="{ row }">
            <template v-if="can('Stock Update')">
                <Button variant="ghost" size="sm" :href="route('stock.receive', { product: row.id })">{{ t('Receive') }}</Button>
                <Button variant="ghost" size="sm" :href="route('stock.adjust', { product: row.id })">{{ t('Adjust') }}</Button>
            </template>
            <Button v-if="can('Stock View List')" variant="ghost" size="sm" :href="route('stock.movements', { filter: { product_id: row.id } })">{{ t('History') }}</Button>
        </template>
    </DataTable>
</template>
