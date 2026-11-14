<script setup lang="ts">
import { computed } from 'vue';

import ApexChart from '@/Components/dashboard/ApexChart.vue';
import DataTable from '@/Components/table/DataTable.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import ChannelCard from '../../Components/ChannelCard.vue';
import ReportCard from '../../Components/ReportCard.vue';
import ReportEmpty from '../../Components/ReportEmpty.vue';
import ReportFilterBar from '../../Components/ReportFilterBar.vue';
import type { BucketChart, ReportFilterProps, ReportOption } from '../../Components/types';

defineOptions({ layout: AdminLayout });

interface ChannelRow {
    id: string;
    bucket: string;
    label: string;
    b2c_orders: number;
    b2c_net: string;
    b2b_orders: number;
    b2b_net: string;
    net: string;
    b2b_share: number;
}

interface Channel {
    key: 'b2c' | 'b2b';
    label: string;
    summary: Record<string, string | number>;
    changes: Record<string, number | null>;
    share: { net: number; orders: number };
}

const props = defineProps<{
    filters: ReportFilterProps;
    branches: ReportOption[];
    channels: Channel[];
    chart: BucketChart & { b2c: number[]; b2b: number[] };
    table: TablePayload<ChannelRow>;
    compare: { from: string; to: string };
    export_url: string;
}>();

const only = ['filters', 'branches', 'channels', 'compare', 'chart', 'table', 'export_url', 'flash'];

const empty = computed(() => [...props.chart.b2c, ...props.chart.b2b].every((value) => value === 0));
const series = computed(() => [
    { name: t('Store (B2C)'), data: props.chart.b2c },
    { name: t('Wholesale (B2B)'), data: props.chart.b2b },
]);

const columns = computed<TableColumn[]>(() => [
    { key: 'label', label: t('Period'), sort: 'bucket' },
    { key: 'b2c_orders', label: t('Store orders'), sort: 'b2c_orders' },
    { key: 'b2c_net', label: t('Store net sales'), sort: 'b2c_net' },
    { key: 'b2b_orders', label: t('Wholesale orders'), sort: 'b2b_orders' },
    { key: 'b2b_net', label: t('Wholesale net sales'), sort: 'b2b_net' },
    { key: 'net', label: t('Net sales'), sort: 'net' },
    { key: 'b2b_share', label: t('Wholesale share') },
]);

const filterList = computed<TableFilter[]>(() => [
    { type: 'date-range', key: 'date', label: t('Period') },
    ...(props.branches.length ? [{ key: 'branch', label: t('Branch'), options: props.branches }] : []),
    { key: 'interval', label: t('Interval'), options: [{ value: 'day', label: t('Daily') }, { value: 'month', label: t('Monthly') }] },
]);
</script>

<template>
    <PageHeader :title="t('Store and wholesale')" :description="t('The same figures for buyers (B2C) and merchants (B2B), their shares, and the change against the period before.')" />

    <ReportFilterBar :filters="filters" :branches="branches" intervals :export-url="export_url" />

    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <ChannelCard v-for="channel in channels" :key="channel.key" :channel="channel" />
        </div>
        <p class="-mt-3 text-xs text-muted">{{ t('Changes are against :from to :to.', { from: compare.from, to: compare.to }) }}</p>

        <ReportCard :title="t('Net sales by channel')" :description="filters.interval === 'month' ? t('Per month') : t('Per day')">
            <ReportEmpty v-if="empty" :message="t('No sales in this period')" />
            <div v-else class="custom-scrollbar max-w-full overflow-x-auto">
                <div class="min-w-162.5 xl:min-w-full">
                    <ApexChart type="bar" :height="260" :series="series" :categories="chart.categories" />
                </div>
            </div>
        </ReportCard>

        <DataTable :table="table" :columns="columns" :filters="filterList" :searchable="false" :only="only" :heading="t('By period')" :empty-title="t('No sales in this period')">
            <template #cell-label="{ row }"><span class="font-medium whitespace-nowrap text-ink">{{ row.label }}</span></template>
            <template #cell-b2b_share="{ row }"><span class="tabular">{{ row.b2b_share }}%</span></template>
        </DataTable>
    </div>
</template>
