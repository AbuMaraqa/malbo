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
import type { BucketChart, ReportFilterProps, ReportKpi, ReportOption } from '../../Components/types';

defineOptions({ layout: AdminLayout });

interface SalesRow {
    id: string;
    bucket: string;
    label: string;
    orders: number;
    units: number;
    gross: string;
    discounts: string;
    delivery: string;
    returns: string;
    net: string;
    total: string;
}

type Money = 'gross' | 'discounts' | 'delivery' | 'returns' | 'net' | 'total' | 'aov';

const props = defineProps<{
    filters: ReportFilterProps;
    branches: ReportOption[];
    summary: Record<Money, string> & { orders: number; units: number; returned_units: number };
    changes: Record<string, number | null>;
    pending: { orders: number; value: string };
    compare: { from: string; to: string };
    chart: BucketChart & { net: number[]; orders: number[] };
    table: TablePayload<SalesRow>;
    export_url: string;
}>();

const only = ['filters', 'branches', 'summary', 'changes', 'pending', 'compare', 'chart', 'table', 'export_url', 'flash'];

const kpis = computed<ReportKpi[]>(() => [
    { key: 'net', label: t('Net sales'), value: props.summary.net, change: props.changes.net },
    { key: 'orders', label: t('Orders'), value: props.summary.orders, change: props.changes.orders },
    { key: 'units', label: t('Units sold'), value: props.summary.units, change: props.changes.units },
    { key: 'aov', label: t('Average order value'), value: props.summary.aov },
    { key: 'gross', label: t('Gross sales'), value: props.summary.gross, change: props.changes.gross },
    { key: 'discounts', label: t('Discounts'), value: props.summary.discounts, change: props.changes.discounts },
    { key: 'returns', label: t('Returns'), value: props.summary.returns, change: props.changes.returns },
    { key: 'delivery', label: t('Delivery fees'), value: props.summary.delivery, hint: `${t('Total sales')}: ${props.summary.total}. ${t('Orders returned without being delivered earn no fee.')}` },
]);

const empty = computed(() => props.chart.orders.every((value) => value === 0));
const series = computed(() => [{ name: t('Net sales'), data: props.chart.net }]);

const columns = computed<TableColumn[]>(() => [
    { key: 'label', label: t('Period'), sort: 'bucket' },
    { key: 'orders', label: t('Orders'), sort: 'orders' },
    { key: 'units', label: t('Units'), sort: 'units' },
    { key: 'gross', label: t('Gross sales'), sort: 'gross' },
    { key: 'discounts', label: t('Discounts'), toggleable: true },
    { key: 'delivery', label: t('Delivery fees'), toggleable: true },
    { key: 'returns', label: t('Returns'), sort: 'returns' },
    { key: 'net', label: t('Net sales'), sort: 'net' },
    { key: 'total', label: t('Total sales'), toggleable: true, hidden: true },
]);

const filterList = computed<TableFilter[]>(() => [
    { type: 'date-range', key: 'date', label: t('Period') },
    { key: 'channel', label: t('Channel'), options: [{ value: 'b2c', label: t('Store (B2C)') }, { value: 'b2b', label: t('Wholesale (B2B)') }] },
    ...(props.branches.length ? [{ key: 'branch', label: t('Branch'), options: props.branches }] : []),
    { key: 'interval', label: t('Interval'), options: [{ value: 'day', label: t('Daily') }, { value: 'month', label: t('Monthly') }] },
]);
</script>

<template>
    <PageHeader :title="t('Sales report')" :description="t('Orders MALBO confirmed, on the day they were placed. Returns count against the day of their order.')" />

    <ReportFilterBar :filters="filters" channels :branches="branches" intervals :export-url="export_url" />

    <div class="space-y-6">
        <div>
            <ReportKpis :kpis="kpis" />
            <p class="mt-2 text-xs text-muted">{{ t('Changes are against :from to :to.', { from: compare.from, to: compare.to }) }}</p>
        </div>

        <p v-if="pending.orders > 0" class="rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/25 dark:bg-warning-500/10 dark:text-warning-400">
            {{ t(':count orders worth :value are waiting for confirmation and are not counted yet.', { count: pending.orders, value: pending.value }) }}
        </p>

        <ReportCard :title="t('Net sales')" :description="filters.interval === 'month' ? t('Per month') : t('Per day')">
            <ReportEmpty v-if="empty" :message="t('No sales in this period')" />
            <div v-else class="custom-scrollbar max-w-full overflow-x-auto">
                <div class="min-w-162.5 xl:min-w-full">
                    <ApexChart type="area" :height="260" :series="series" :categories="chart.categories" />
                </div>
            </div>
        </ReportCard>

        <DataTable
            :table="table"
            :columns="columns"
            :filters="filterList"
            :searchable="false"
            :only="only"
            :heading="t('Sales by period')"
            :empty-title="t('No sales in this period')"
            :empty-text="t('Confirmed orders appear here on the day they were placed.')"
        >
            <template #cell-label="{ row }"><span class="font-medium whitespace-nowrap text-ink">{{ row.label }}</span></template>
            <template #cell-net="{ row }"><span class="font-medium whitespace-nowrap">{{ row.net }}</span></template>
        </DataTable>
    </div>
</template>
