<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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

interface MerchantRow {
    id: number;
    shop_name: string;
    status: string;
    status_label: string | null;
    status_tone: 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand' | null;
    tier: string | null;
    sales_rep: string | null;
    orders: number;
    units: number;
    net: string;
    aov: string | null;
    last_order: string | null;
    balance: string;
    credit_limit: string | null;
    ledger_url: string;
}

const props = defineProps<{
    filters: ReportFilterProps;
    statuses: ReportOption[];
    tiers: ReportOption[];
    sales_reps: ReportOption[];
    totals: { merchants: number; active: number; orders: number; net: string; balance: string };
    chart: { labels: string[]; net: number[] };
    table: TablePayload<MerchantRow>;
    export_url: string;
}>();

const only = ['filters', 'statuses', 'tiers', 'sales_reps', 'totals', 'chart', 'table', 'export_url', 'flash'];

const kpis = computed<ReportKpi[]>(() => [
    { key: 'net', label: t('Wholesale net sales'), value: props.totals.net },
    { key: 'orders', label: t('Wholesale orders'), value: props.totals.orders },
    { key: 'merchants', label: t('Merchants who ordered'), value: props.totals.active, hint: t('of :count', { count: props.totals.merchants }) },
    { key: 'discounts', label: t('Balance due'), value: props.totals.balance },
]);

const series = computed(() => [{ name: t('Net sales'), data: props.chart.net }]);

const columns = computed<TableColumn[]>(() => [
    { key: 'shop_name', label: t('Shop'), sort: 'shop_name' },
    { key: 'tier', label: t('Tier'), toggleable: true },
    { key: 'sales_rep', label: t('Sales rep'), toggleable: true, hidden: true },
    { key: 'orders', label: t('Orders'), sort: 'orders' },
    { key: 'units', label: t('Units'), sort: 'units', toggleable: true },
    { key: 'net', label: t('Net sales'), sort: 'net' },
    { key: 'aov', label: t('Average order value'), sort: 'aov' },
    { key: 'last_order', label: t('Last order'), sort: 'last_order_at' },
    { key: 'balance', label: t('Balance due'), sort: 'balance' },
    { key: 'credit_limit', label: t('Credit limit'), toggleable: true, hidden: true },
]);

const filterList = computed<TableFilter[]>(() => [
    { type: 'date-range', key: 'date', label: t('Period') },
    { key: 'status', label: t('Status'), options: props.statuses },
    ...(props.tiers.length ? [{ key: 'tier', label: t('Tier'), options: props.tiers }] : []),
    ...(props.sales_reps.length ? [{ key: 'sales_rep', label: t('Sales rep'), options: props.sales_reps }] : []),
]);
</script>

<template>
    <PageHeader :title="t('Merchant performance')" :description="t('Each merchant\'s confirmed wholesale orders in the period, their last order, and what they owe now.')" />

    <ReportFilterBar :filters="filters" :export-url="export_url" />

    <div class="space-y-6">
        <ReportKpis :kpis="kpis" />

        <ReportCard :title="t('Top 10 by net sales')">
            <ReportEmpty v-if="chart.labels.length === 0" :message="t('No wholesale sales in this period')" />
            <div v-else class="custom-scrollbar max-w-full overflow-x-auto">
                <div class="min-w-162.5 xl:min-w-full">
                    <ApexChart type="bar" :height="260" :series="series" :categories="chart.labels" />
                </div>
            </div>
        </ReportCard>

        <DataTable :table="table" :columns="columns" :filters="filterList" :only="only" :heading="t('Merchants')" :empty-title="t('No merchants')">
            <template #cell-shop_name="{ row }">
                <Link :href="row.ledger_url" class="font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400">{{ row.shop_name }}</Link>
                <p v-if="row.status_label" class="mt-1"><Badge :tone="row.status_tone ?? 'neutral'" dot>{{ row.status_label }}</Badge></p>
            </template>
            <template #cell-tier="{ row }">{{ row.tier ?? '—' }}</template>
            <template #cell-sales_rep="{ row }">{{ row.sales_rep ?? '—' }}</template>
            <template #cell-aov="{ row }"><span class="whitespace-nowrap">{{ row.aov ?? '—' }}</span></template>
            <template #cell-last_order="{ row }"><span class="whitespace-nowrap text-muted">{{ row.last_order ?? t('Never') }}</span></template>
            <template #cell-credit_limit="{ row }">{{ row.credit_limit ?? '—' }}</template>
        </DataTable>
    </div>
</template>
