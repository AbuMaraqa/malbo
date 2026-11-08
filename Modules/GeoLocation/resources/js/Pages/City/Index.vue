<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@/lib/icons';
import { computed } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
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
    governorate: string | null;
    country: string | null;
    latitude: string | null;
    longitude: string | null;
    population: number | null;
    type: string | null;
    is_capital: boolean;
    capital_type: string | null;
    locales: string[];
}

const props = defineProps<{
    table: TablePayload<Row>;
    countries: Option[];
    governorates: Option[];
    types: Option[];
    capitalTypes: Option[];
    locales: string[];
}>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'governorate', label: t('Governorate') },
    { key: 'country', label: t('Country'), toggleable: true },
    { key: 'latitude', label: t('Latitude'), class: 'tabular', toggleable: true, hidden: true },
    { key: 'longitude', label: t('Longitude'), class: 'tabular', toggleable: true, hidden: true },
    { key: 'population', label: t('Population'), sort: 'population', class: 'tabular' },
    { key: 'type', label: t('Type'), sort: 'type' },
    { key: 'is_capital', label: t('Is Capital') },
    { key: 'capital_type', label: t('Capital Type'), toggleable: true },
    { key: 'locales', label: t('Locale'), toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'name', label: t('Name') },
    { key: 'country_id', label: t('Country'), options: props.countries },
    { key: 'governorate_id', label: t('Governorate'), options: props.governorates },
    { key: 'type', label: t('Type'), options: props.types },
    { type: 'boolean', key: 'is_capital', label: t('Is Capital'), trueLabel: t('Yes'), falseLabel: t('No') },
    { key: 'capital_type', label: t('Capital Type'), options: props.capitalTypes },
    { type: 'number-range', key: 'population', label: t('Population') },
    { key: 'locale', label: t('Locale'), options: props.locales.map((locale) => ({ value: locale, label: locale.toUpperCase() })) },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.name }), description: t('This cannot be undone.') })) {
        router.delete(route('cities.destroy', row.id), { preserveScroll: true });
    }
}

async function removeMany(ids: (number | string)[]): Promise<void> {
    if (await confirm({ title: t('Delete :count items?', { count: ids.length }), description: t('This cannot be undone.') })) {
        router.delete(route('cities.destroy-many'), { data: { ids }, preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Cities List')">
        <template #actions>
            <Button v-if="can('City Create')" variant="primary" :href="route('cities.add')">{{ t('Add City') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :selectable="can('City Delete')" :empty-title="t('No cities found')" :empty-text="t('Create a new city by clicking the button below')">
        <template #cell-name="{ row }">
            <Link v-if="can('City Update')" :href="route('cities.edit', row.id)" class="font-medium text-brand-600 dark:text-brand-400 hover:underline">{{ row.name }}</Link>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-is_capital="{ row }">
            <Badge :tone="row.is_capital ? 'success' : 'neutral'">{{ row.is_capital ? t('Yes') : t('No') }}</Badge>
        </template>
        <template #cell-locales="{ row }">
            <span class="text-muted uppercase">{{ row.locales.join(', ') }}</span>
        </template>
        <template #actions="{ row }">
            <Button v-if="can('City Update')" variant="ghost" size="icon" :href="route('cities.edit', row.id)" :aria-label="t('Edit')"><Pencil class="size-4" /></Button>
            <Button v-if="can('City Delete')" variant="ghost" size="icon" :aria-label="t('Delete')" @click="remove(row)"><Trash2 class="size-4" /></Button>
        </template>
        <template #bulk="{ selected }">
            <Button variant="danger" size="sm" @click="removeMany(selected)">{{ t('Delete') }}</Button>
        </template>
    </DataTable>
</template>
