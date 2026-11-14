<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand';

interface Row {
    id: number;
    order_id: number;
    order_number: string | null;
    order_deleted: boolean;
    customer: string | null;
    courier: string;
    tracking_number: string | null;
    status: string;
    status_label: string;
    status_tone: Tone;
    cost: string;
    shipped_at: string | null;
    delivered_at: string | null;
    created_at: string | null;
    created_by: string | null;
}

const props = defineProps<{ table: TablePayload<Row>; statuses: Option[]; couriers: Option[] }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'id', label: '#', sort: 'id' },
    { key: 'order_number', label: t('Order Number') },
    { key: 'customer', label: t('Customer') },
    { key: 'courier', label: t('Courier') },
    { key: 'tracking_number', label: t('Tracking number') },
    { key: 'status', label: t('Status'), sort: 'status' },
    { key: 'cost', label: t('Shipping cost'), sort: 'cost', class: 'tabular', toggleable: true },
    { key: 'shipped_at', label: t('Shipped at'), sort: 'shipped_at', toggleable: true },
    { key: 'delivered_at', label: t('Delivered at'), sort: 'delivered_at', toggleable: true, hidden: true },
    { key: 'created_at', label: t('Created at'), sort: 'created_at', toggleable: true },
    { key: 'created_by', label: t('Created by'), toggleable: true, hidden: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'order_number', label: t('Order Number'), placeholder: t('Search by Order Number') },
    { key: 'status', label: t('Status'), options: props.statuses },
    { key: 'courier', label: t('Courier'), options: props.couriers },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);
</script>

<template>
    <PageHeader :title="t('Shipments')" :count="table.meta.total" :description="t('Make a shipment from the order page; moving it moves the order.')" />

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No shipments')" :empty-text="t('Shipments made from the order pages show here.')">
        <template #cell-order_number="{ row }">
            <Link v-if="can('Order Update') && !row.order_deleted" :href="route('orders.edit', row.order_id)" class="font-medium text-brand-600 hover:underline dark:text-brand-400" dir="ltr">{{ row.order_number }}</Link>
            <span v-else dir="ltr">{{ row.order_number }}</span>
        </template>
        <template #cell-tracking_number="{ row }">
            <span dir="ltr">{{ row.tracking_number ?? '—' }}</span>
        </template>
        <template #cell-status="{ row }">
            <Badge :tone="row.status_tone">{{ row.status_label }}</Badge>
        </template>
        <template #cell-shipped_at="{ row }">
            <span dir="ltr">{{ row.shipped_at }}</span>
        </template>
        <template #cell-delivered_at="{ row }">
            <span dir="ltr">{{ row.delivered_at }}</span>
        </template>
    </DataTable>
</template>
