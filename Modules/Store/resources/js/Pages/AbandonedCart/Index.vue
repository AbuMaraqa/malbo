<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import DataTable from '@/Components/table/DataTable.vue';
import RowMenu from '@/Components/table/RowMenu.vue';
import Badge from '@/Components/ui/Badge.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { Eye } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface AbandonedCartRow {
    id: number;
    contact: string | null;
    customer: string | null;
    items: number;
    lines: number;
    value: string;
    locale: string | null;
    last_activity: string | null;
    last_activity_at: string | null;
    reminders_sent: number;
    recovered: boolean;
    order_number: string | null;
}

defineProps<{ table: TablePayload<AbandonedCartRow> }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'contact', label: t('Contact') },
    { key: 'items', label: t('Items'), sort: 'items_count' },
    { key: 'value', label: t('Value') },
    { key: 'last_activity_at', label: t('Last activity'), sort: 'last_activity_at' },
    { key: 'reminders_sent', label: t('Reminders sent'), sort: 'reminders_sent' },
    { key: 'recovered', label: t('Recovered') },
    { key: 'locale', label: t('Language'), toggleable: true, hidden: true },
]);

const filters = computed<TableFilter[]>(() => [
    { key: 'status', label: t('Status'), options: [{ value: 'open', label: t('Not ordered') }, { value: 'recovered', label: t('Recovered') }] },
    { type: 'boolean', key: 'reminded', label: t('Reminded'), trueLabel: t('Yes'), falseLabel: t('No') },
    { key: 'locale', label: t('Language'), options: [{ value: 'ar', label: 'العربية' }, { value: 'en', label: 'English' }, { value: 'he', label: 'עברית' }] },
    { type: 'date-range', key: 'last_activity_at', label: t('Last activity') },
]);
</script>

<template>
    <PageHeader :title="t('Abandoned carts')" :description="t('Carts left without an order by buyers who asked to be reminded. Values are at today\'s prices.')" />

    <DataTable
        :table="table"
        :columns="columns"
        :filters="filters"
        :empty-title="t('No abandoned carts')"
        :empty-text="t('Carts appear here when a buyer who asked to be reminded leaves without ordering.')"
    >
        <template #cell-contact="{ row }">
            <Link :href="route('abandoned-carts.show', row.id)" class="font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400" dir="ltr">{{ row.contact ?? '—' }}</Link>
            <p v-if="row.customer" class="text-sm text-muted">{{ row.customer }}</p>
        </template>
        <template #cell-value="{ row }">
            <span class="font-medium whitespace-nowrap">{{ row.value }}</span>
        </template>
        <template #cell-last_activity_at="{ row }">
            <span class="whitespace-nowrap text-muted" :title="row.last_activity_at ?? undefined">{{ row.last_activity }}</span>
        </template>
        <template #cell-recovered="{ row }">
            <Badge v-if="row.recovered" tone="success" dot>{{ t('Recovered') }}<template v-if="row.order_number"> · {{ row.order_number }}</template></Badge>
            <span v-else class="text-muted">—</span>
        </template>
        <template #actions="{ row }">
            <RowMenu>
                <DropdownItem @select="router.visit(route('abandoned-carts.show', row.id))"><Eye />{{ t('View') }}</DropdownItem>
            </RowMenu>
        </template>
    </DataTable>
</template>
