<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import RecordActions from '../parts/RecordActions.vue';
import { useRecordDelete } from '../parts/useRecordDelete';

defineOptions({ layout: AdminLayout });

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand';

interface Row {
    id: number;
    order_number: string;
    customer: string | null;
    branch: string | null;
    sub_total: string;
    discount: string;
    delivery_fee: string;
    total: string;
    status: string | null;
    status_tone: Tone;
    payment_status: string | null;
    payment_status_tone: Tone;
    payment_method: string | null;
    delivery_address: string | null;
    contact_phone: string | null;
    notes: string | null;
    created_at: string | null;
    items: { name: string | null; quantity: number; price: string; total_price: string }[];
}

const props = defineProps<{
    table: TablePayload<Row>;
    statuses: Option[];
    paymentStatuses: Option[];
    paymentMethods: Option[];
    deliveryTypes: Option[];
    branches: Option[];
}>();

const columns = computed<TableColumn[]>(() => [
    { key: 'order_number', label: t('Order Number'), sort: 'order_number' },
    { key: 'customer', label: t('Name') },
    { key: 'branch', label: t('Branch'), toggleable: true, hidden: true },
    { key: 'sub_total', label: t('Sub Total'), sort: 'sub_total', class: 'tabular', toggleable: true },
    { key: 'discount', label: t('Discount'), sort: 'discount', class: 'tabular', toggleable: true },
    { key: 'delivery_fee', label: t('Delivery Fee'), sort: 'delivery_fee', class: 'tabular', toggleable: true },
    { key: 'total', label: t('Total'), sort: 'total', class: 'tabular' },
    { key: 'status', label: t('Status'), sort: 'status' },
    { key: 'payment_status', label: t('Payment Status'), sort: 'payment_status' },
    { key: 'created_at', label: t('Order Date'), sort: 'created_at', toggleable: true, hidden: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'order_number', label: t('Order Number'), placeholder: t('Search by Order Number') },
    { type: 'text', key: 'customer', label: t('Name'), placeholder: t('Search by name') },
    { key: 'status', label: t('Status'), options: props.statuses },
    { key: 'payment_status', label: t('Payment Status'), options: props.paymentStatuses },
    { key: 'payment_method', label: t('Payment Method'), options: props.paymentMethods },
    { key: 'delivery_type', label: t('Delivery Type'), options: props.deliveryTypes },
    { key: 'branch_id', label: t('Branch'), options: props.branches },
    { type: 'number-range', key: 'total', label: t('Total') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

const itemLines = (row: Row): string => row.items.map((item) => `${item.name ?? t('Deleted product')} x ${item.quantity} = ${item.total_price}`).join('\n');

const { remove, removeMany } = useRecordDelete('orders');
</script>

<template>
    <PageHeader :title="t('Orders List')">
        <template #actions>
            <Button v-if="can('Order Create')" variant="primary" :href="route('orders.add')">{{ t('Add Order') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :selectable="can('Order Delete')" :empty-title="t('No orders found')" :empty-text="t('Create a new order by clicking the button below')">
        <template #cell-order_number="{ row }">
            <Link v-if="can('Order Update')" :href="route('orders.edit', row.id)" class="font-medium text-brand-600 dark:text-brand-400 hover:underline" dir="ltr">{{ row.order_number }}</Link>
            <span v-else dir="ltr">{{ row.order_number }}</span>
        </template>
        <template #cell-status="{ row }">
            <Badge :tone="row.status_tone">{{ row.status }}</Badge>
        </template>
        <template #cell-payment_status="{ row }">
            <Badge :tone="row.payment_status_tone">{{ row.payment_status }}</Badge>
        </template>
        <template #empty>
            <Button v-if="can('Order Create')" variant="primary" :href="route('orders.add')">{{ t('Add Order') }}</Button>
        </template>
        <template #actions="{ row }">
            <RecordActions
                entity="Order"
                routes="orders"
                :id="row.id"
                :title="row.order_number"
                :edit-href="route('orders.edit', row.id)"
                :fields="[
                    { label: t('Customer Name'), value: row.customer },
                    { label: t('Order Number'), value: row.order_number },
                    { label: t('Products'), value: itemLines(row) },
                    { label: t('Subtotal'), value: row.sub_total },
                    { label: t('Discount'), value: row.discount },
                    { label: t('Delivery Fee'), value: row.delivery_fee },
                    { label: t('Grand Total'), value: row.total },
                    { label: t('Order Status'), value: row.status },
                    { label: t('Payment Method'), value: row.payment_method },
                    { label: t('Payment Status'), value: row.payment_status },
                    { label: t('Delivery Address'), value: row.delivery_address },
                    { label: t('Contact Phone'), value: row.contact_phone },
                    { label: t('Notes'), value: row.notes },
                    { label: t('Order Date'), value: row.created_at },
                ]"
                @delete="remove(row.id, row.order_number)"
            />
        </template>
        <template #bulk="{ selected }">
            <Button variant="danger" size="sm" @click="removeMany(selected)">{{ t('Delete') }}</Button>
        </template>
    </DataTable>
</template>
