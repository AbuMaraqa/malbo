<script setup lang="ts">
import { computed } from 'vue';

import ApexChart from '@/Components/dashboard/ApexChart.vue';
import DataTable from '@/Components/table/DataTable.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import ReportCard from '../../Components/ReportCard.vue';
import ReportEmpty from '../../Components/ReportEmpty.vue';
import ReportFilterBar from '../../Components/ReportFilterBar.vue';
import ReportKpis from '../../Components/ReportKpis.vue';
import type { ReportFilterProps, ReportKpi, ReportOption } from '../../Components/types';

defineOptions({ layout: AdminLayout });

interface ProductRow {
    id: string;
    name: string;
    sku: string | null;
    bundles: number | null;
    orders: number;
    units: number;
    gross: string;
    discounts: string;
    returns: string;
    net: string;
    returned_units: number;
}

const props = defineProps<{
    filters: ReportFilterProps;
    by: 'product' | 'category' | 'color' | 'size' | 'bundle';
    groupings: ReportOption[];
    branches: ReportOption[];
    categories: ReportOption[];
    totals: { orders: number; units: number; gross: string; discounts: string; returns: string; net: string };
    chart: { labels: string[]; net: number[]; units: number[] };
    table: TablePayload<ProductRow>;
    export_url: string;
}>();

const only = ['filters', 'by', 'groupings', 'branches', 'categories', 'totals', 'chart', 'table', 'export_url', 'flash'];

const kpis = computed<ReportKpi[]>(() => [
    { key: 'net', label: t('Net sales'), value: props.totals.net },
    { key: 'units', label: t('Units sold'), value: props.totals.units },
    { key: 'gross', label: t('Gross sales'), value: props.totals.gross },
    { key: 'returns', label: t('Returns'), value: props.totals.returns },
]);

const series = computed(() => [{ name: t('Net sales'), data: props.chart.net }]);

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: props.groupings.find((option) => option.value === props.by)?.label ?? t('Name'), sort: 'name' },
    ...(props.by === 'product' || props.by === 'bundle' ? [{ key: 'sku', label: t('SKU'), toggleable: true }] : []),
    ...(props.by === 'bundle' ? [{ key: 'bundles', label: t('Sets sold') }] : []),
    { key: 'orders', label: t('Orders'), sort: 'orders' },
    { key: 'units', label: t('Units'), sort: 'units' },
    { key: 'gross', label: t('Gross sales'), sort: 'gross' },
    { key: 'discounts', label: t('Discounts'), toggleable: true },
    { key: 'returns', label: t('Returns'), sort: 'returns' },
    { key: 'net', label: t('Net sales'), sort: 'net' },
    { key: 'returned_units', label: t('Returned units'), sort: 'returned_units', toggleable: true },
]);

const filterList = computed<TableFilter[]>(() => [
    { type: 'date-range', key: 'date', label: t('Period') },
    { key: 'by', label: t('Group by'), options: props.groupings },
    { key: 'channel', label: t('Channel'), options: [{ value: 'b2c', label: t('Store (B2C)') }, { value: 'b2b', label: t('Wholesale (B2B)') }] },
    ...(props.categories.length ? [{ key: 'category', label: t('Category'), options: props.categories }] : []),
    ...(props.branches.length ? [{ key: 'branch', label: t('Branch'), options: props.branches }] : []),
    { type: 'number-range', key: 'units', label: t('Units') },
]);
</script>

<template>
    <PageHeader
        :title="t('Sales by product')"
        :description="t('Order lines of confirmed orders. Pieces of a set count under their own product, colour and size; a product in several categories counts in each, the totals count it once.')"
    />

    <ReportFilterBar :filters="filters" channels :branches="branches" :categories="categories" :groupings="groupings" :grouping="by" :export-url="export_url" />

    <div class="space-y-6">
        <ReportKpis :kpis="kpis" />

        <ReportCard :title="t('Top 10 by net sales')">
            <ReportEmpty v-if="chart.labels.length === 0" :message="t('No sales in this period')" />
            <div v-else class="custom-scrollbar max-w-full overflow-x-auto">
                <div class="min-w-162.5 xl:min-w-full">
                    <ApexChart type="bar" :height="280" :series="series" :categories="chart.labels" />
                </div>
            </div>
        </ReportCard>

        <DataTable :table="table" :columns="columns" :filters="filterList" :only="only" :heading="t('Sales by product')" :empty-title="t('No sales in this period')">
            <template #cell-name="{ row }"><span class="font-medium text-ink">{{ row.name }}</span></template>
            <template #cell-sku="{ row }"><span class="text-muted" dir="ltr">{{ row.sku ?? '—' }}</span></template>
            <template #cell-net="{ row }"><span class="font-medium whitespace-nowrap">{{ row.net }}</span></template>
        </DataTable>
    </div>
</template>
