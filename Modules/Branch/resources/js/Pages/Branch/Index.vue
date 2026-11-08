<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { Pencil, Trash2 } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    name: string;
    description: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    country: string | null;
    city: string | null;
    opening_time: string | null;
    closing_time: string | null;
    latitude: string | null;
    longitude: string | null;
    status: number;
    status_label: string;
    open_days: string[];
    locales: string[];
}

const props = defineProps<{
    table: TablePayload<Row>;
    statuses: Option[];
    countries: Option[];
    cities: Option[];
    locales: string[];
}>();

const OPEN = 1;

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'description', label: t('Description'), toggleable: true, hidden: true },
    { key: 'address', label: t('Address'), toggleable: true },
    { key: 'phone', label: t('Phone') },
    { key: 'email', label: t('Email'), toggleable: true, hidden: true },
    { key: 'country', label: t('Country'), toggleable: true },
    { key: 'city', label: t('City') },
    { key: 'opening_time', label: t('Opening Time'), sort: 'opening_time', class: 'tabular' },
    { key: 'closing_time', label: t('Closing Time'), sort: 'closing_time', class: 'tabular' },
    { key: 'open_days', label: t('Weekly Schedule'), toggleable: true, hidden: true },
    { key: 'latitude', label: t('Latitude'), class: 'tabular', toggleable: true, hidden: true },
    { key: 'longitude', label: t('Longitude'), class: 'tabular', toggleable: true, hidden: true },
    { key: 'status', label: t('Status'), sort: 'status' },
    { key: 'locales', label: t('Locale'), toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'name', label: t('Name') },
    { key: 'status', label: t('Status'), options: props.statuses },
    { key: 'country_id', label: t('Country'), options: props.countries },
    { key: 'city_id', label: t('City'), options: props.cities },
    { type: 'text', key: 'phone', label: t('Phone') },
    { type: 'text', key: 'email', label: t('Email') },
    { key: 'locale', label: t('Locale'), options: props.locales.map((locale) => ({ value: locale, label: locale.toUpperCase() })) },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.name }), description: t('This cannot be undone.') })) {
        router.delete(route('branches.destroy', row.id), { preserveScroll: true });
    }
}

async function removeMany(ids: (number | string)[]): Promise<void> {
    if (await confirm({ title: t('Delete :count items?', { count: ids.length }), description: t('This cannot be undone.') })) {
        router.delete(route('branches.destroy-many'), { data: { ids }, preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Branches List')">
        <template #actions>
            <Button v-if="can('Branch Create')" variant="primary" :href="route('branches.add')">{{ t('Add Branch') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :selectable="can('Branch Delete')" :empty-title="t('No branches found')" :empty-text="t('Create a new branch by clicking the button below')">
        <template #cell-name="{ row }">
            <Link v-if="can('Branch Update')" :href="route('branches.edit', row.id)" class="font-medium text-brand-600 dark:text-brand-400 hover:underline">{{ row.name }}</Link>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-status="{ row }">
            <Badge :tone="row.status === OPEN ? 'success' : 'danger'">{{ row.status_label }}</Badge>
        </template>
        <template #cell-open_days="{ row }">{{ row.open_days.join('، ') }}</template>
        <template #cell-locales="{ row }">
            <span class="text-muted uppercase">{{ row.locales.join(', ') }}</span>
        </template>
        <template #actions="{ row }">
            <Button v-if="can('Branch Update')" variant="ghost" size="icon" :href="route('branches.edit', row.id)" :aria-label="t('Edit')"><Pencil class="size-4" /></Button>
            <Button v-if="can('Branch Delete')" variant="ghost" size="icon" :aria-label="t('Delete')" @click="remove(row)"><Trash2 class="size-4" /></Button>
        </template>
        <template #bulk="{ selected }">
            <Button variant="danger" size="sm" @click="removeMany(selected)">{{ t('Delete') }}</Button>
        </template>
    </DataTable>
</template>
