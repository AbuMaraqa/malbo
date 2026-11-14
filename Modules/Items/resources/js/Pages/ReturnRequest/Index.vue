<script setup lang="ts">
import RowMenu from '@/Components/table/RowMenu.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import { Pencil } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import ReturnSettingsCard from './ReturnSettingsCard.vue';

defineOptions({ layout: AdminLayout });

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand';

interface Row {
    id: number;
    order_number: string | null;
    customer: string | null;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    status_tone: Tone;
    reason: string;
    units: number;
    refund_amount: string;
    refunded: boolean;
    created_at: string | null;
}

const props = defineProps<{ table: TablePayload<Row>; statuses: Option[]; types: Option[]; windowDays: number }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'id', label: '#', sort: 'id' },
    { key: 'order_number', label: t('Order Number') },
    { key: 'customer', label: t('Customer') },
    { key: 'type_label', label: t('Type') },
    { key: 'status', label: t('Status'), sort: 'status' },
    { key: 'reason', label: t('Reason'), toggleable: true },
    { key: 'units', label: t('Units'), class: 'tabular', toggleable: true },
    { key: 'refund_amount', label: t('Refund'), sort: 'refund_amount', class: 'tabular', toggleable: true },
    { key: 'refunded', label: t('Refunded'), toggleable: true },
    { key: 'created_at', label: t('Created at'), sort: 'created_at', toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'order_number', label: t('Order Number'), placeholder: t('Search by Order Number') },
    { type: 'boolean', key: 'open', label: t('Open'), trueLabel: t('Still being handled'), falseLabel: t('Closed') },
    { key: 'status', label: t('Status'), options: props.statuses },
    { key: 'type', label: t('Type'), options: props.types },
    { type: 'boolean', key: 'refunded', label: t('Refunded'), trueLabel: t('Refunded'), falseLabel: t('Not refunded') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);
</script>

<template>
    <PageHeader :title="t('Return requests')" :count="table.meta.total">
        <template #actions>
            <Button v-if="can('Return Request Create')" variant="primary" :href="route('return-requests.add')">{{ t('Add return request') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No return requests')" :empty-text="t('Requests from buyers and staff show here.')">
        <template #cell-id="{ row }">
            <Link v-if="can('Return Request Update')" :href="route('return-requests.edit', row.id)" class="font-medium text-brand-600 hover:underline dark:text-brand-400">#{{ row.id }}</Link>
            <span v-else>#{{ row.id }}</span>
        </template>
        <template #cell-order_number="{ row }">
            <span dir="ltr">{{ row.order_number }}</span>
        </template>
        <template #cell-status="{ row }">
            <Badge :tone="row.status_tone">{{ row.status_label }}</Badge>
        </template>
        <template #cell-refunded="{ row }">
            <Badge v-if="row.type === 'return'" :tone="row.refunded ? 'success' : 'neutral'">{{ row.refunded ? t('Refunded') : t('Not refunded') }}</Badge>
        </template>
        <template #actions="{ row }">
            <RowMenu>
                <DropdownItem v-if="can('Return Request Update')" :href="route('return-requests.edit', row.id)"><Pencil />{{ t('Edit') }}</DropdownItem>
            </RowMenu>
        </template>
    </DataTable>

    <ReturnSettingsCard v-if="can('Return Request Update')" :days="windowDays" class="mt-6" />
</template>
