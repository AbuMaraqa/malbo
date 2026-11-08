<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import RecordActions from '../parts/RecordActions.vue';
import { useRecordDelete } from '../parts/useRecordDelete';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    type: string | null;
    locales: string[];
}

const props = defineProps<{ table: TablePayload<Row>; types: Option[] }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'slug', label: t('Slug'), sort: 'slug' },
    { key: 'configuration', label: t('Configure Attribute') },
    { key: 'type', label: t('Type'), sort: 'type' },
    { key: 'locales', label: t('Locale'), toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'name', label: t('Name'), placeholder: t('Search by name') },
    { key: 'type', label: t('Type'), options: props.types },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

const { remove, removeMany } = useRecordDelete('attributes');
</script>

<template>
    <PageHeader :title="t('Attributes List')">
        <template #actions>
            <Button v-if="can('Attribute Create')" variant="primary" :href="route('attributes.add')">{{ t('Add Attribute') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :selectable="can('Attribute Delete')" :empty-title="t('No attributes found')" :empty-text="t('Add the attributes your products vary by, like size or color.')">
        <template #cell-name="{ row }">
            <Link v-if="can('Attribute Update')" :href="route('attributes.edit', row.id)" class="font-medium text-brand-600 dark:text-brand-400 hover:underline">{{ row.name }}</Link>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-configuration="{ row }">
            <Link v-if="can('Attribute Update')" :href="`${route('attributes.edit', row.id)}#values`" class="text-brand-600 dark:text-brand-400 hover:underline">{{ t('Configure Attribute') }}</Link>
        </template>
        <template #cell-locales="{ row }">
            <span class="text-muted uppercase">{{ row.locales.join(', ') }}</span>
        </template>
        <template #actions="{ row }">
            <RecordActions
                entity="Attribute"
                routes="attributes"
                :id="row.id"
                :title="row.name"
                :edit-href="route('attributes.edit', row.id)"
                :fields="[
                    { label: t('Name'), value: row.name },
                    { label: t('Slug'), value: row.slug },
                    { label: t('Description'), value: row.description },
                ]"
                @delete="remove(row.id, row.name)"
            />
        </template>
        <template #bulk="{ selected }">
            <Button variant="danger" size="sm" @click="removeMany(selected)">{{ t('Delete') }}</Button>
        </template>
    </DataTable>
</template>
