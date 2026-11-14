<script setup lang="ts">
import { computed } from 'vue';

import DataTable from '@/Components/table/DataTable.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import ReportCard from '../../Components/ReportCard.vue';
import ReportEmpty from '../../Components/ReportEmpty.vue';
import ReportFilterBar from '../../Components/ReportFilterBar.vue';
import type { ReportFilterProps, ReportOption } from '../../Components/types';

defineOptions({ layout: AdminLayout });

interface SlowRow {
    id: number;
    name: string;
    sku: string | null;
    stock: number;
    items_in_stock: number;
    units: number;
    net: string;
    last_sold: string | null;
}

const props = defineProps<{
    filters: ReportFilterProps;
    categories: ReportOption[];
    best: { name: string; sku: string | null; units: number; net: string }[];
    table: TablePayload<SlowRow>;
    export_url: string;
}>();

const only = ['filters', 'categories', 'best', 'table', 'export_url', 'flash'];
const top = computed(() => Math.max(1, ...props.best.map((row) => row.units)));

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Product'), sort: 'name' },
    { key: 'sku', label: t('SKU'), toggleable: true },
    { key: 'stock', label: t('Stock on hand'), sort: 'stock' },
    { key: 'items_in_stock', label: t('Items in stock'), toggleable: true },
    { key: 'units', label: t('Units sold'), sort: 'units' },
    { key: 'net', label: t('Net sales'), sort: 'net' },
    { key: 'last_sold', label: t('Last sold') },
]);

const filterList = computed<TableFilter[]>(() => [
    { type: 'date-range', key: 'date', label: t('Period') },
    { key: 'channel', label: t('Channel'), options: [{ value: 'b2c', label: t('Store (B2C)') }, { value: 'b2b', label: t('Wholesale (B2B)') }] },
    ...(props.categories.length ? [{ key: 'category', label: t('Category'), options: props.categories }] : []),
    { type: 'number-range', key: 'units', label: t('Units sold') },
]);
</script>

<template>
    <PageHeader :title="t('Best and slow sellers')" :description="t('The products that sold most in the period, and the published products with stock on hand that sold least.')" />

    <ReportFilterBar :filters="filters" channels :categories="categories" :export-url="export_url" />

    <div class="space-y-6">
        <ReportCard :title="t('Best sellers')" :description="t('Top 10 by units sold')">
            <ReportEmpty v-if="best.length === 0" :message="t('No sales in this period')" />
            <ol v-else class="space-y-3">
                <li v-for="(row, index) in best" :key="`${row.name}-${index}`" class="flex items-center gap-3">
                    <span class="tabular flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">{{ index + 1 }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="truncate font-medium text-ink">{{ row.name }}</span>
                            <span class="tabular shrink-0 text-muted">{{ t(':count units', { count: row.units }) }} · {{ row.net }}</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                            <div class="h-full rounded-full bg-brand-500" :style="{ width: `${(row.units / top) * 100}%` }" />
                        </div>
                    </div>
                </li>
            </ol>
        </ReportCard>

        <DataTable
            :table="table"
            :columns="columns"
            :filters="filterList"
            :only="only"
            :heading="t('Slow sellers')"
            :empty-title="t('No products with stock on hand')"
            :empty-text="t('Published products whose stock is tracked appear here, those that sold least first.')"
        >
            <template #cell-name="{ row }"><span class="font-medium text-ink">{{ row.name }}</span></template>
            <template #cell-sku="{ row }"><span class="text-muted" dir="ltr">{{ row.sku ?? '—' }}</span></template>
            <template #cell-units="{ row }">
                <span class="tabular font-medium" :class="row.units === 0 ? 'text-error-600 dark:text-error-400' : ''">{{ row.units }}</span>
            </template>
            <template #cell-last_sold="{ row }"><span class="whitespace-nowrap text-muted">{{ row.last_sold ?? t('Never') }}</span></template>
        </DataTable>
    </div>
</template>
