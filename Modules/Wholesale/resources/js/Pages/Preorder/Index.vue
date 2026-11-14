<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import DataTable from '@/Components/table/DataTable.vue';
import RowMenu from '@/Components/table/RowMenu.vue';
import Badge from '@/Components/ui/Badge.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { Pencil } from '@/lib/icons';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface PreorderedOrder {
    id: number;
    number: string;
    shop: string | null;
    quantity: number;
    preorder: number;
    placed_at: string | null;
    url: string;
}

interface Row {
    id: number;
    name: string;
    options: string | null;
    sku: string | null;
    preordered: number;
    orders_count: number;
    incoming_quantity: number;
    expected_at: string | null;
    stock: number;
    prices_url: string;
    orders: PreorderedOrder[];
}

defineProps<{ table: TablePayload<Row> }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Product') },
    { key: 'preordered', label: t('Pre-ordered'), sort: 'preordered' },
    { key: 'incoming_quantity', label: t('Incoming') },
    { key: 'expected_at', label: t('Expected on'), sort: 'incoming_expected_at' },
    { key: 'stock', label: t('Stock'), toggleable: true },
    { key: 'orders', label: t('Orders') },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'date-range', key: 'expected_at', label: t('Expected on') },
    { type: 'boolean', key: 'received', label: t('Goods received'), trueLabel: t('Received'), falseLabel: t('On their way') },
]);
</script>

<template>
    <PageHeader :title="t('Pre-orders')" :count="table.meta.total" />

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No pre-orders')" :empty-text="t('Merchants reserve incoming goods from the wholesale portal.')">
        <template #cell-name="{ row }">
            <p class="font-medium text-ink">{{ row.name }}</p>
            <p class="text-xs text-muted">
                <span v-if="row.options">{{ row.options }} · </span><span dir="ltr">{{ row.sku }}</span>
            </p>
        </template>
        <template #cell-preordered="{ row }">
            <span class="font-semibold text-ink">{{ row.preordered }}</span>
            <span class="text-xs text-muted"> · {{ t(':count orders', { count: row.orders_count }) }}</span>
        </template>
        <template #cell-incoming_quantity="{ row }">
            <Badge v-if="row.incoming_quantity > 0" tone="info">{{ row.incoming_quantity }}</Badge>
            <Badge v-else tone="success">{{ t('Received') }}</Badge>
        </template>
        <template #cell-expected_at="{ row }">
            <span v-if="row.expected_at" dir="ltr">{{ row.expected_at }}</span>
            <span v-else class="text-muted">—</span>
        </template>
        <template #cell-stock="{ row }">
            <span :class="row.stock < 0 ? 'text-error-600 dark:text-error-400' : 'text-ink'" dir="ltr">{{ row.stock }}</span>
        </template>
        <template #cell-orders="{ row }">
            <ul class="space-y-0.5 text-sm">
                <li v-for="order in row.orders" :key="order.id">
                    <Link :href="order.url" class="font-medium text-brand-600 hover:underline dark:text-brand-400" dir="ltr">{{ order.number }}</Link>
                    <span class="text-muted"> · {{ order.shop }} · {{ t(':count pre-ordered', { count: order.preorder }) }}</span>
                </li>
            </ul>
        </template>
        <template #actions="{ row }">
            <RowMenu>
                <DropdownItem :href="row.prices_url"><Pencil />{{ t('Incoming goods') }}</DropdownItem>
            </RowMenu>
        </template>
    </DataTable>
</template>
