<script setup lang="ts">
import { computed } from 'vue';

import ApexChart from '@/Components/dashboard/ApexChart.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
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

interface ReturnRow {
    id: string;
    name: string;
    sold: number;
    returned: number;
    exchanged: number;
    returns: string;
    rate: number;
}

const props = defineProps<{
    filters: ReportFilterProps;
    by: 'product' | 'category';
    groupings: ReportOption[];
    categories: ReportOption[];
    summary: { sold: number; returned: number; exchanged: number; returns: string; requests: number; return_rate: number; exchange_rate: number };
    reasons: { key: string; reason: string; type: string; type_label: string; units: number }[];
    table: TablePayload<ReturnRow>;
    export_url: string;
}>();

const only = ['filters', 'by', 'groupings', 'categories', 'summary', 'reasons', 'table', 'export_url', 'flash'];

const kpis = computed<ReportKpi[]>(() => [
    { key: 'returns', label: t('Return rate'), value: `${props.summary.return_rate}%`, hint: t(':returned of :sold units', { returned: props.summary.returned, sold: props.summary.sold }) },
    { key: 'net', label: t('Returns'), value: props.summary.returns },
    { key: 'units', label: t('Rate of exchanges'), value: `${props.summary.exchange_rate}%`, hint: t(':count units', { count: props.summary.exchanged }) },
    { key: 'orders', label: t('Completed requests'), value: props.summary.requests },
]);

const reasonLabels = computed(() => props.reasons.map((row) => (row.key === 'order_returned' ? row.reason : `${row.reason} (${row.type_label})`)));
const reasonSeries = computed(() => props.reasons.map((row) => row.units));

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: props.by === 'category' ? t('Category') : t('Product'), sort: 'name' },
    { key: 'sold', label: t('Units sold'), sort: 'units' },
    { key: 'returned', label: t('Returned units'), sort: 'returned_units' },
    { key: 'exchanged', label: t('Exchanged units'), sort: 'exchanged_units' },
    { key: 'returns', label: t('Returns'), sort: 'returns' },
    { key: 'rate', label: t('Return rate'), sort: 'rate' },
]);

const filterList = computed<TableFilter[]>(() => [
    { type: 'date-range', key: 'date', label: t('Period') },
    { key: 'by', label: t('Group by'), options: props.groupings },
    { key: 'channel', label: t('Channel'), options: [{ value: 'b2c', label: t('Store (B2C)') }, { value: 'b2b', label: t('Wholesale (B2B)') }] },
    ...(props.categories.length ? [{ key: 'category', label: t('Category'), options: props.categories }] : []),
    { type: 'number-range', key: 'sold', label: t('Units sold') },
]);

function tone(rate: number): 'danger' | 'warning' | 'neutral' {
    return rate >= 20 ? 'danger' : rate >= 10 ? 'warning' : 'neutral';
}
</script>

<template>
    <PageHeader :title="t('Return rate')" :description="t('Units returned out of the units sold in the period (counted on the day of their order). Exchanges have their own rate.')" />

    <ReportFilterBar :filters="filters" channels :categories="categories" :groupings="groupings" :grouping="by" :export-url="export_url" />

    <div class="space-y-6">
        <ReportKpis :kpis="kpis" />

        <ReportCard :title="t('Reasons')" :description="t('Units by reason, from completed requests')">
            <ReportEmpty v-if="reasons.length === 0" :message="t('Nothing came back in this period')" />
            <ApexChart v-else type="donut" :height="280" :series="reasonSeries" :labels="reasonLabels" />
        </ReportCard>

        <DataTable :table="table" :columns="columns" :filters="filterList" :only="only" :heading="t('Return rate')" :empty-title="t('No sales in this period')">
            <template #cell-name="{ row }"><span class="font-medium text-ink">{{ row.name }}</span></template>
            <template #cell-rate="{ row }"><Badge :tone="tone(row.rate)">{{ row.rate }}%</Badge></template>
        </DataTable>
    </div>
</template>
