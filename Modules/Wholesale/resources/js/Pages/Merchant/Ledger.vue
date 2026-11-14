<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import { DollarSign, Pencil, Printer } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import AdjustmentModal from './parts/AdjustmentModal.vue';
import LedgerTotals from './parts/LedgerTotals.vue';
import PaymentModal from './parts/PaymentModal.vue';
import type { LedgerCredit, LedgerRow, LedgerTotals as Totals } from './parts/types';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    merchant: { id: number; shop_name: string };
    table: TablePayload<LedgerRow>;
    totals: Totals;
    credit: LedgerCredit;
    types: Option[];
    methods: Option[];
    today: string;
    statementRange: { from: string; to: string };
}>();

const paying = ref(false);
const adjusting = ref(false);

const statementUrl = computed(() => {
    const filter = props.table.query.filter as Record<string, string | undefined>;

    return route('merchants.statement', {
        merchant: props.merchant.id,
        from: filter.entry_date_from ?? props.statementRange.from,
        to: filter.entry_date_to ?? props.statementRange.to,
    });
});

const columns = computed<TableColumn[]>(() => [
    { key: 'entry_date', label: t('Date'), sort: 'entry_date' },
    { key: 'type', label: t('Type') },
    { key: 'details', label: t('Details') },
    { key: 'amount', label: t('Amount'), sort: 'amount' },
    { key: 'running_balance', label: t('Balance') },
    { key: 'by', label: t('Recorded by'), toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { key: 'type', label: t('Type'), options: props.types },
    { key: 'method', label: t('Payment method'), options: props.methods },
    { type: 'date-range', key: 'entry_date', label: t('Date') },
]);
</script>

<template>
    <PageHeader
        :title="t('Account of :shop', { shop: merchant.shop_name })"
        :parents="[
            { title: t('Merchants'), href: route('merchants.index') },
            { title: merchant.shop_name, href: route('merchants.edit', merchant.id) },
        ]"
    >
        <template #actions>
            <Button :href="statementUrl" external new-tab><Printer />{{ t('Print statement') }}</Button>
            <Button v-if="can('Merchant Payment Create')" @click="adjusting = true"><Pencil />{{ t('Adjustment') }}</Button>
            <Button v-if="can('Merchant Payment Create')" variant="primary" @click="paying = true"><DollarSign />{{ t('Record a payment') }}</Button>
        </template>
    </PageHeader>

    <LedgerTotals class="mb-4" :totals="totals" :credit="credit" />
    <p v-if="totals.unpaid_invoices" class="mb-4 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/25 dark:bg-warning-500/10 dark:text-warning-400">
        {{ t(':count delivered orders are not marked paid. Record the payment, or mark the order paid.', { count: totals.unpaid_invoices }) }}
    </p>

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No entries yet')" :empty-text="t('Delivered orders, returns, payments and adjustments show here.')">
        <template #cell-entry_date="{ row }">
            <span dir="ltr">{{ row.entry_date }}</span>
        </template>
        <template #cell-type="{ row }">
            <Badge :tone="row.type_tone">{{ row.type_label }}</Badge>
            <Badge v-if="row.unpaid" tone="warning" class="ms-1">{{ t('Not paid') }}</Badge>
        </template>
        <template #cell-details="{ row }">
            <p v-if="row.order">
                <Link :href="row.order.url" class="font-medium text-brand-600 hover:underline dark:text-brand-400" dir="ltr">{{ row.order.number }}</Link>
            </p>
            <p v-if="row.method || row.reference" class="text-sm text-ink">
                {{ row.method }}<template v-if="row.method && row.reference"> · </template><span v-if="row.reference" dir="ltr">{{ row.reference }}</span>
            </p>
            <p v-if="row.note" class="text-xs text-muted">{{ row.note }}</p>
            <a v-if="row.receipt_url" :href="row.receipt_url" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">{{ t('Receipt') }}</a>
        </template>
        <template #cell-amount="{ row }">
            <span :class="row.amount < 0 ? 'text-success-600 dark:text-success-400' : 'text-ink'" class="font-medium" dir="ltr">{{ row.amount_label }}</span>
        </template>
        <template #cell-running_balance="{ row }">
            <span class="font-semibold text-ink" dir="ltr">{{ row.running_label }}</span>
        </template>
        <template #cell-by="{ row }">
            <span v-if="row.by">{{ row.by }}</span>
            <span v-else class="text-muted">{{ t('Automatic') }}</span>
        </template>
    </DataTable>

    <PaymentModal v-model:open="paying" :merchant-id="merchant.id" :methods="methods" :today="today" />
    <AdjustmentModal v-model:open="adjusting" :merchant-id="merchant.id" :today="today" />
</template>
