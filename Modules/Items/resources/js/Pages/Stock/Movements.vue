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

defineOptions({ layout: AdminLayout });

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand';

interface Row {
    id: number;
    created_at: string | null;
    product_id: number;
    product_name: string;
    sku: string | null;
    edit_product_id: number;
    product_deleted: boolean;
    type: string;
    type_label: string;
    type_tone: Tone;
    quantity: number;
    balance_after: number;
    order_id: number | null;
    order_number: string | null;
    order_deleted: boolean;
    note: string | null;
    by: string | null;
}

const props = defineProps<{ table: TablePayload<Row>; types: Option[] }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'created_at', label: t('Date'), sort: 'created_at' },
    { key: 'product_name', label: t('Product') },
    { key: 'sku', label: t('SKU'), toggleable: true },
    { key: 'type', label: t('Type') },
    { key: 'quantity', label: t('Change'), sort: 'quantity', class: 'tabular' },
    { key: 'balance_after', label: t('Stock after'), sort: 'balance_after', class: 'tabular' },
    { key: 'order_number', label: t('Order Number') },
    { key: 'note', label: t('Note'), toggleable: true },
    { key: 'by', label: t('By'), toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'product', label: t('Product'), placeholder: t('Name or SKU') },
    { key: 'type', label: t('Type'), options: props.types },
    { type: 'text', key: 'order', label: t('Order Number'), placeholder: t('Search by Order Number') },
    { type: 'date-range', key: 'created_at', label: t('Date') },
]);
</script>

<template>
    <PageHeader :title="t('Stock movements')" :count="table.meta.total" :description="t('Every change to the stock: sales, cancellations, returns, goods received and adjustments.')">
        <template v-if="can('Stock Update')" #actions>
            <Button :href="route('stock.adjust')">{{ t('Stock adjustment') }}</Button>
            <Button variant="primary" :href="route('stock.receive')">{{ t('Receive goods') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No stock movements')" :empty-text="t('Sales, returns and goods received show here.')">
        <template #cell-created_at="{ row }">
            <span dir="ltr">{{ row.created_at }}</span>
        </template>
        <template #cell-product_name="{ row }">
            <Link v-if="can('Product Update') && !row.product_deleted" :href="route('products.edit', row.edit_product_id)" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ row.product_name }}</Link>
            <span v-else>{{ row.product_name }}</span>
        </template>
        <template #cell-sku="{ row }">
            <span dir="ltr">{{ row.sku }}</span>
        </template>
        <template #cell-type="{ row }">
            <Badge :tone="row.type_tone">{{ row.type_label }}</Badge>
        </template>
        <template #cell-quantity="{ row }">
            <span dir="ltr" :class="row.quantity < 0 ? 'text-error-600 dark:text-error-400' : 'text-success-600 dark:text-success-400'">{{ row.quantity > 0 ? `+${row.quantity}` : row.quantity }}</span>
        </template>
        <template #cell-order_number="{ row }">
            <template v-if="row.order_number">
                <Link v-if="can('Order Update') && !row.order_deleted" :href="route('orders.edit', row.order_id)" class="font-medium text-brand-600 hover:underline dark:text-brand-400" dir="ltr">{{ row.order_number }}</Link>
                <span v-else dir="ltr">{{ row.order_number }}</span>
            </template>
        </template>
    </DataTable>
</template>
