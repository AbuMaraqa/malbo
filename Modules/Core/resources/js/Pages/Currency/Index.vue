<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@/lib/icons';
import { computed } from 'vue';

import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    name: string;
    code: string;
    symbol: string;
    exchange_rate: string;
    is_active: boolean;
    locales: string[];
}

defineProps<{ table: TablePayload<Row> }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Name') },
    { key: 'code', label: t('Code'), sort: 'code' },
    { key: 'symbol', label: t('Symbol') },
    { key: 'exchange_rate', label: t('Exchange rate'), sort: 'exchange_rate', class: 'tabular' },
    { key: 'is_active', label: t('Status') },
    { key: 'locales', label: t('Locale'), toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'boolean', key: 'is_active', label: t('Status'), trueLabel: t('Active'), falseLabel: t('Inactive') },
    { key: 'symbol_position', label: t('Position'), options: [{ value: 'before', label: t('Before') }, { value: 'after', label: t('After') }] },
    { type: 'number-range', key: 'exchange_rate', label: t('Exchange rate') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.name }), description: t('This cannot be undone.') })) {
        router.delete(route('currencies.destroy', row.id), { preserveScroll: true });
    }
}

async function removeMany(ids: (number | string)[]): Promise<void> {
    if (await confirm({ title: t('Delete :count items?', { count: ids.length }), description: t('This cannot be undone.') })) {
        router.delete(route('currencies.destroy-many'), { data: { ids }, preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Currencies List')">
        <template #actions>
            <Button v-if="can('Currency Create')" variant="primary" :href="route('currencies.add')">{{ t('Add Currency') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :selectable="can('Currency Delete')" :empty-title="t('No currencies found')" :empty-text="t('Add the currencies your store sells in.')">
        <template #cell-name="{ row }">
            <Link v-if="can('Currency Update')" :href="route('currencies.edit', row.id)" class="font-medium text-brand-600 dark:text-brand-400 hover:underline">{{ row.name }}</Link>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-is_active="{ row }">
            <Badge :tone="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? t('Active') : t('Inactive') }}</Badge>
        </template>
        <template #cell-locales="{ row }">
            <span class="text-muted uppercase">{{ row.locales.join(', ') }}</span>
        </template>
        <template #actions="{ row }">
            <Button v-if="can('Currency Update')" variant="ghost" size="icon" :href="route('currencies.edit', row.id)" :aria-label="t('Edit')"><Pencil class="size-4" /></Button>
            <Button v-if="can('Currency Delete')" variant="ghost" size="icon" :aria-label="t('Delete')" @click="remove(row)"><Trash2 class="size-4" /></Button>
        </template>
        <template #bulk="{ selected }">
            <Button variant="danger" size="sm" @click="removeMany(selected)">{{ t('Delete') }}</Button>
        </template>
    </DataTable>
</template>
