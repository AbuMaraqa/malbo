<script setup lang="ts">
import RowMenu from '@/Components/table/RowMenu.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import DropdownSeparator from '@/Components/ui/DropdownSeparator.vue';
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@/lib/icons';
import { computed } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
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
    phone_code: string | null;
    currency: string | null;
    locales: string[];
}

const props = defineProps<{
    table: TablePayload<Row>;
    codes: string[];
    phoneCodes: string[];
    currencies: Option[];
    locales: string[];
}>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'code', label: t('Code'), sort: 'code' },
    { key: 'phone_code', label: t('Phone Code'), sort: 'phone_code', class: 'tabular' },
    { key: 'currency', label: t('Currency') },
    { key: 'locales', label: t('Locale'), toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'name', label: t('Name') },
    { key: 'code', label: t('Code'), options: props.codes.map((code) => ({ value: code, label: code })) },
    { key: 'phone_code', label: t('Phone Code'), options: props.phoneCodes.map((code) => ({ value: code, label: code })) },
    { key: 'currency_id', label: t('Currency'), options: props.currencies },
    { key: 'locale', label: t('Locale'), options: props.locales.map((locale) => ({ value: locale, label: locale.toUpperCase() })) },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.name }), description: t('This cannot be undone.') })) {
        router.delete(route('countries.destroy', row.id), { preserveScroll: true });
    }
}

async function removeMany(ids: (number | string)[]): Promise<void> {
    if (await confirm({ title: t('Delete :count items?', { count: ids.length }), description: t('This cannot be undone.') })) {
        router.delete(route('countries.destroy-many'), { data: { ids }, preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Countries List')" :count="table.meta.total">
        <template #actions>
            <Button v-if="can('Country Create')" variant="primary" :href="route('countries.add')">{{ t('Add Country') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :selectable="can('Country Delete')" :empty-title="t('No countries found')" :empty-text="t('Create a new country by clicking the button below')">
        <template #cell-name="{ row }">
            <Link v-if="can('Country Update')" :href="route('countries.edit', row.id)" class="font-medium text-brand-600 dark:text-brand-400 hover:underline">{{ row.name }}</Link>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-locales="{ row }">
            <span class="text-muted uppercase">{{ row.locales.join(', ') }}</span>
        </template>
        <template #actions="{ row }">
            <RowMenu>
                <DropdownItem v-if="can('Country Update')" :href="route('countries.edit', row.id)"><Pencil />{{ t('Edit') }}</DropdownItem>
                <DropdownSeparator v-if="can('Country Delete')" />
                <DropdownItem v-if="can('Country Delete')" destructive @select="remove(row)"><Trash2 />{{ t('Delete') }}</DropdownItem>
            </RowMenu>
        </template>
        <template #bulk="{ selected }">
            <Button variant="danger" size="sm" @click="removeMany(selected)">{{ t('Delete') }}</Button>
        </template>
    </DataTable>
</template>
