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

interface Row {
    id: number;
    image: string;
    name: string;
    description: string | null;
    type: string | null;
    locales: string[];
}

const props = defineProps<{ table: TablePayload<Row>; types: Option[] }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'image', label: t('Image'), toggleable: true, hidden: true },
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'description', label: t('Description'), toggleable: true },
    { key: 'type', label: t('Type'), sort: 'type' },
    { key: 'locales', label: t('Locale'), toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'name', label: t('Name'), placeholder: t('Search by name') },
    { key: 'type', label: t('Type'), options: props.types },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

const { remove, removeMany } = useRecordDelete('addons');
</script>

<template>
    <PageHeader :title="t('Addons List')">
        <template #actions>
            <Button v-if="can('Addon Create')" variant="primary" :href="route('addons.add')">{{ t('Add Addon') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :selectable="can('Addon Delete')" :empty-title="t('No addons found')" :empty-text="t('Create a new addon by clicking the button below')">
        <template #cell-image="{ row }">
            <img v-if="row.image" :src="row.image" alt="" class="size-12 rounded-control object-cover" />
        </template>
        <template #cell-name="{ row }">
            <Link v-if="can('Addon Update')" :href="route('addons.edit', row.id)" class="font-medium text-brand-600 dark:text-brand-400 hover:underline">{{ row.name }}</Link>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-type="{ row }">
            <Badge tone="brand">{{ row.type }}</Badge>
        </template>
        <template #cell-locales="{ row }">
            <span class="text-muted uppercase">{{ row.locales.join(', ') }}</span>
        </template>
        <template #empty>
            <Button v-if="can('Addon Create')" variant="primary" :href="route('addons.add')">{{ t('Add Addon') }}</Button>
        </template>
        <template #actions="{ row }">
            <RecordActions
                entity="Addon"
                routes="addons"
                :id="row.id"
                :title="row.name"
                :edit-href="route('addons.edit', row.id)"
                :fields="[
                    { label: t('Name'), value: row.name },
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
