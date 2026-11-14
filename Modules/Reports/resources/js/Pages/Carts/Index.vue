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
import type { BucketChart, ReportFilterProps, ReportKpi } from '../../Components/types';

defineOptions({ layout: AdminLayout });

interface CartRow {
    id: string;
    bucket: string;
    label: string;
    started: number;
    converted: number;
    abandoned: number;
    reachable: number;
    value: string;
    reminded: number;
    recovered: number;
    recovered_value: string;
}

const props = defineProps<{
    filters: ReportFilterProps;
    abandoned_after_minutes: number;
    summary: {
        started: number;
        converted: number;
        abandoned: number;
        reachable: number;
        value: string;
        reminded: number;
        recovered: number;
        recovered_value: string;
        conversion_rate: number;
        abandonment_rate: number;
        recovery_rate: number;
    };
    reminders: { channel: string; label: string; sent: number }[];
    chart: BucketChart & { started: number[]; abandoned: number[]; recovered: number[] };
    table: TablePayload<CartRow>;
    export_url: string;
}>();

const only = ['filters', 'abandoned_after_minutes', 'summary', 'reminders', 'chart', 'table', 'export_url', 'flash'];

const kpis = computed<ReportKpi[]>(() => [
    { key: 'orders', label: t('Carts started'), value: props.summary.started, hint: t(':rate% became an order', { rate: props.summary.conversion_rate }) },
    { key: 'returns', label: t('Abandoned'), value: props.summary.abandoned, hint: t(':count can be reminded', { count: props.summary.reachable }) },
    { key: 'discounts', label: t('Abandoned value'), value: props.summary.value },
    { key: 'net', label: t('Recovered'), value: props.summary.recovered, hint: t(':rate% of reminded carts', { rate: props.summary.recovery_rate }) },
    { key: 'total', label: t('Recovered value'), value: props.summary.recovered_value },
    { key: 'units', label: t('Reminded'), value: props.summary.reminded },
]);

const empty = computed(() => props.chart.started.every((value) => value === 0));
const series = computed(() => [
    { name: t('Carts started'), data: props.chart.started },
    { name: t('Abandoned'), data: props.chart.abandoned },
    { name: t('Recovered'), data: props.chart.recovered },
]);

const columns = computed<TableColumn[]>(() => [
    { key: 'label', label: t('Period'), sort: 'bucket' },
    { key: 'started', label: t('Carts started'), sort: 'started' },
    { key: 'converted', label: t('Became an order'), sort: 'converted' },
    { key: 'abandoned', label: t('Abandoned'), sort: 'abandoned' },
    { key: 'reachable', label: t('Can be reminded'), toggleable: true },
    { key: 'value', label: t('Abandoned value'), sort: 'value' },
    { key: 'reminded', label: t('Reminded') },
    { key: 'recovered', label: t('Recovered'), sort: 'recovered' },
    { key: 'recovered_value', label: t('Recovered value') },
]);

const filterList = computed<TableFilter[]>(() => [
    { type: 'date-range', key: 'date', label: t('Period') },
    { key: 'interval', label: t('Interval'), options: [{ value: 'day', label: t('Daily') }, { value: 'month', label: t('Monthly') }] },
]);
</script>

<template>
    <PageHeader
        :title="t('Abandoned carts report')"
        :description="t('Store carts started in the period. A cart is abandoned when it has items, no order, and has been quiet for :minutes minutes; its value counts all its lines at today\'s prices.', { minutes: abandoned_after_minutes })"
    />

    <ReportFilterBar :filters="filters" intervals :export-url="export_url" />

    <div class="space-y-6">
        <ReportKpis :kpis="kpis" />

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <ReportCard class="xl:col-span-2" :title="t('Carts')" :description="filters.interval === 'month' ? t('Per month') : t('Per day')">
                <ReportEmpty v-if="empty" :message="t('No carts were started in this period')" />
                <div v-else class="custom-scrollbar max-w-full overflow-x-auto">
                    <div class="min-w-162.5 xl:min-w-full">
                        <ApexChart type="line" :height="260" :series="series" :categories="chart.categories" />
                    </div>
                </div>
            </ReportCard>

            <ReportCard :title="t('Reminders sent')" :description="t('In the period, by channel')">
                <ReportEmpty v-if="reminders.length === 0" :message="t('No reminders were sent in this period')" />
                <dl v-else class="divide-y divide-line text-sm">
                    <div v-for="row in reminders" :key="row.channel" class="flex items-center justify-between py-2.5">
                        <dt class="text-muted">{{ row.label }}</dt>
                        <dd class="tabular font-semibold text-ink">{{ row.sent.toLocaleString('en') }}</dd>
                    </div>
                </dl>
            </ReportCard>
        </div>

        <DataTable :table="table" :columns="columns" :filters="filterList" :searchable="false" :only="only" :heading="t('By period')" :empty-title="t('No carts were started in this period')">
            <template #cell-label="{ row }"><span class="font-medium whitespace-nowrap text-ink">{{ row.label }}</span></template>
        </DataTable>
    </div>
</template>
