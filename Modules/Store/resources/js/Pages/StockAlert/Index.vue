<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface StockAlertRow {
    id: number;
    product_id: number;
    name: string;
    options: string | null;
    sku: string | null;
    stock: number | null;
    edit_url: string | null;
    waiting: number;
    notified: number;
    last_requested_at: string | null;
}

defineProps<{ table: TablePayload<StockAlertRow> }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Product') },
    { key: 'stock', label: t('Stock') },
    { key: 'waiting', label: t('Waiting'), sort: 'waiting_count' },
    { key: 'notified', label: t('Notified'), sort: 'notified_count' },
    { key: 'last_requested_at', label: t('Last request'), sort: 'last_requested_at' },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'boolean', key: 'waiting', label: t('Buyers waiting'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'date-range', key: 'created_at', label: t('Requested on') },
]);
</script>

<template>
    <PageHeader :title="t('Stock alerts')" :description="t('Buyers who asked to be told when an item is back, per item. They are told by themselves as soon as stock arrives.')" />

    <DataTable
        :table="table"
        :columns="columns"
        :filters="filters"
        :empty-title="t('No requests yet')"
        :empty-text="t('Requests from the product page appear here.')"
    >
        <template #cell-name="{ row }">
            <component :is="row.edit_url ? Link : 'span'" :href="row.edit_url ?? undefined" class="font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400">{{ row.name }}</component>
            <p class="text-sm text-muted">
                <span v-if="row.options">{{ row.options }}</span>
                <span v-if="row.sku" dir="ltr"> · {{ row.sku }}</span>
            </p>
        </template>
        <template #cell-stock="{ row }">
            <span v-if="row.stock === null" class="text-muted">—</span>
            <span v-else :class="row.stock > 0 ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400'">{{ row.stock }}</span>
        </template>
        <template #cell-waiting="{ row }">
            <Badge :tone="row.waiting > 0 ? 'warning' : 'neutral'">{{ row.waiting }}</Badge>
        </template>
        <template #cell-last_requested_at="{ row }">
            <span class="whitespace-nowrap text-muted">{{ row.last_requested_at }}</span>
        </template>
    </DataTable>
</template>
