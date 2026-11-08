<script setup lang="ts">
import { computed } from 'vue';

import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

import MetricsGrid from './parts/MetricsGrid.vue';
import NewUsersChart from './parts/NewUsersChart.vue';
import OrdersByStatusChart from './parts/OrdersByStatusChart.vue';
import OrdersPerMonthChart from './parts/OrdersPerMonthChart.vue';
import QuickAccess from './parts/QuickAccess.vue';
import RecentOrdersTable from './parts/RecentOrdersTable.vue';
import type { LabelledSeries, Metric, MonthlySeries, QuickLink, RecentOrders } from './parts/types';
import UsersByRoleChart from './parts/UsersByRoleChart.vue';
import WidgetEmpty from './parts/WidgetEmpty.vue';

defineOptions({ layout: AdminLayout });

/** A widget the user has no permission for is null. */
const props = defineProps<{
    metrics: Metric[];
    quickLinks: QuickLink[];
    ordersPerMonth: MonthlySeries | null;
    usersByRole: LabelledSeries | null;
    newUsersPerMonth: MonthlySeries | null;
    ordersByStatus: LabelledSeries | null;
    recentOrders: RecentOrders | null;
}>();

const hasMainColumn = computed(() => props.metrics.length > 0 || props.ordersPerMonth !== null);
const hasWidgets = computed(() => hasMainColumn.value || props.quickLinks.length > 0 || props.usersByRole !== null);
</script>

<template>
    <PageHeader :title="t('Home')" />

    <WidgetEmpty v-if="!hasWidgets" :message="t('There is nothing on the dashboard for your permissions yet.')" />

    <div v-else class="grid grid-cols-12 gap-4 md:gap-6">
        <div v-if="hasMainColumn" class="col-span-12 space-y-6" :class="{ 'xl:col-span-7': usersByRole }">
            <MetricsGrid v-if="metrics.length" :metrics="metrics" />
            <OrdersPerMonthChart v-if="ordersPerMonth" :chart="ordersPerMonth" />
        </div>

        <div v-if="usersByRole" class="col-span-12" :class="{ 'xl:col-span-5': hasMainColumn }">
            <UsersByRoleChart :chart="usersByRole" />
        </div>

        <div v-if="quickLinks.length" class="col-span-12">
            <QuickAccess :links="quickLinks" />
        </div>

        <div v-if="newUsersPerMonth" class="col-span-12">
            <NewUsersChart :chart="newUsersPerMonth" />
        </div>

        <div v-if="ordersByStatus" class="col-span-12 xl:col-span-5">
            <OrdersByStatusChart :chart="ordersByStatus" />
        </div>

        <div v-if="recentOrders" class="col-span-12 xl:col-span-7">
            <RecentOrdersTable :orders="recentOrders" />
        </div>
    </div>
</template>
