<script setup lang="ts">
import { computed } from 'vue';

import ApexChart from '@/Components/dashboard/ApexChart.vue';
import DataTable from '@/Components/table/DataTable.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { Info } from '@/lib/icons';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import ReportCard from '../../Components/ReportCard.vue';
import ReportEmpty from '../../Components/ReportEmpty.vue';
import ReportFilterBar from '../../Components/ReportFilterBar.vue';
import type { BucketChart, ReportFilterProps } from '../../Components/types';

defineOptions({ layout: AdminLayout });

interface ConversionRow {
    id: string;
    bucket: string;
    label: string;
    b2c_carts: number;
    b2c_converted: number;
    b2c_rate: number;
    b2b_carts: number;
    b2b_converted: number;
    b2b_rate: number;
}

interface Channel {
    key: 'b2c' | 'b2b';
    label: string;
    carts: number;
    converted: number;
    conversion_rate: number;
    orders: number;
    aov: string;
}

const props = defineProps<{
    filters: ReportFilterProps;
    channels: Channel[];
    aov: string;
    chart: BucketChart & { b2c_rate: number[]; b2b_rate: number[]; b2c_aov: number[]; b2b_aov: number[] };
    table: TablePayload<ConversionRow>;
    export_url: string;
}>();

const only = ['filters', 'channels', 'aov', 'chart', 'table', 'export_url', 'flash'];

const rateSeries = computed(() => [
    { name: t('Store (B2C)'), data: props.chart.b2c_rate },
    { name: t('Wholesale (B2B)'), data: props.chart.b2b_rate },
]);
const aovSeries = computed(() => [
    { name: t('Store (B2C)'), data: props.chart.b2c_aov },
    { name: t('Wholesale (B2B)'), data: props.chart.b2b_aov },
]);
const noCarts = computed(() => props.channels.every((channel) => channel.carts === 0));
const noOrders = computed(() => props.channels.every((channel) => channel.orders === 0));

const columns = computed<TableColumn[]>(() => [
    { key: 'label', label: t('Period'), sort: 'bucket' },
    { key: 'b2c_carts', label: t('Store carts'), sort: 'b2c_carts' },
    { key: 'b2c_converted', label: t('Store carts ordered'), sort: 'b2c_converted' },
    { key: 'b2c_rate', label: t('Store conversion') },
    { key: 'b2b_carts', label: t('Wholesale carts'), sort: 'b2b_carts' },
    { key: 'b2b_converted', label: t('Wholesale carts ordered'), sort: 'b2b_converted' },
    { key: 'b2b_rate', label: t('Wholesale conversion') },
]);

const filterList = computed<TableFilter[]>(() => [
    { type: 'date-range', key: 'date', label: t('Period') },
    { key: 'interval', label: t('Interval'), options: [{ value: 'day', label: t('Daily') }, { value: 'month', label: t('Monthly') }] },
]);
</script>

<template>
    <PageHeader :title="t('Conversion and order value')" :description="t('How many started carts became an order, and the average order value, for each channel.')" />

    <ReportFilterBar :filters="filters" intervals :export-url="export_url" />

    <div class="space-y-6">
        <p class="flex items-start gap-2 rounded-lg border border-blue-light-200 bg-blue-light-50 px-4 py-3 text-sm text-blue-light-700 dark:border-blue-light-500/25 dark:bg-blue-light-500/10 dark:text-blue-light-400">
            <Info class="mt-0.5 size-4 shrink-0" />
            {{ t('Site visits are not tracked, so this is cart-to-order conversion: carts started in the period that became an order. It is not the share of visitors who bought.') }}
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <section v-for="channel in channels" :key="channel.key" class="rounded-xl border border-line bg-card p-5 shadow-card">
                <h2 class="text-sm font-medium text-muted">{{ channel.label }}</h2>
                <p class="tabular mt-2 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ channel.conversion_rate }}%</p>
                <p class="mt-1 text-sm text-muted">{{ t(':converted of :carts carts became an order', { converted: channel.converted, carts: channel.carts }) }}</p>
                <p class="mt-4 flex items-center justify-between border-t border-line pt-3 text-sm">
                    <span class="text-muted">{{ t('Average order value') }}</span>
                    <span class="tabular font-semibold text-ink">{{ channel.aov }}</span>
                </p>
                <p class="mt-1 flex items-center justify-between text-sm">
                    <span class="text-muted">{{ t('Confirmed orders') }}</span>
                    <span class="tabular font-medium text-ink">{{ channel.orders.toLocaleString('en') }}</span>
                </p>
            </section>
            <section class="rounded-xl border border-line bg-card p-5 shadow-card">
                <h2 class="text-sm font-medium text-muted">{{ t('Average order value, both channels') }}</h2>
                <p class="tabular mt-2 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ aov }}</p>
                <p class="mt-1 text-sm text-muted">{{ t('Gross sales less discounts, per confirmed order') }}</p>
            </section>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <ReportCard :title="t('Cart conversion')" :description="t('Percent of started carts')">
                <ReportEmpty v-if="noCarts" :message="t('No carts were started in this period')" />
                <ApexChart v-else type="line" :height="240" :series="rateSeries" :categories="chart.categories" />
            </ReportCard>
            <ReportCard :title="t('Average order value')" :description="filters.interval === 'month' ? t('Per month') : t('Per day')">
                <ReportEmpty v-if="noOrders" :message="t('No sales in this period')" />
                <ApexChart v-else type="line" :height="240" :series="aovSeries" :categories="chart.categories" />
            </ReportCard>
        </div>

        <DataTable :table="table" :columns="columns" :filters="filterList" :searchable="false" :only="only" :heading="t('By period')" :empty-title="t('No carts were started in this period')">
            <template #cell-label="{ row }"><span class="font-medium whitespace-nowrap text-ink">{{ row.label }}</span></template>
            <template #cell-b2c_rate="{ row }"><span class="tabular">{{ row.b2c_rate }}%</span></template>
            <template #cell-b2b_rate="{ row }"><span class="tabular">{{ row.b2b_rate }}%</span></template>
        </DataTable>
    </div>
</template>
