<script setup lang="ts">
import { computed } from 'vue';

import DataTable from '@/Components/table/DataTable.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import BadgeFields, { type BadgeForm } from '../parts/BadgeFields.vue';
import ColorBadge from '../parts/ColorBadge.vue';
import RecordActions from '../parts/RecordActions.vue';
import { useRecordDelete } from '../parts/useRecordDelete';
import { useRecordModal } from '../parts/useRecordModal';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    text_color: string;
    background_color: string;
    icon: string | null;
    locales: string[];
    form: { name: string; description: string };
}

defineProps<{ table: TablePayload<Row> }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'slug', label: t('Slug'), sort: 'slug' },
    { key: 'color', label: t('Color') },
    { key: 'icon', label: t('Icon'), toggleable: true },
    { key: 'locales', label: t('Locale'), toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'name', label: t('Name'), placeholder: t('Search by name') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

const { open, editingId, form, add, edit, submit } = useRecordModal<BadgeForm>('labels', () => ({
    name: '',
    slug: '',
    description: '',
    text_color: '#ffffff',
    background_color: '#000000',
}));

const { remove, removeMany } = useRecordDelete('labels');

function editRow(row: Row): void {
    edit(row.id, { ...row.form, slug: row.slug, text_color: row.text_color, background_color: row.background_color });
}
</script>

<template>
    <PageHeader :title="t('Labels List')">
        <template #actions>
            <Button v-if="can('Label Create')" variant="primary" @click="add">{{ t('Add Label') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :selectable="can('Label Delete')" :empty-title="t('No labels found')" :empty-text="t('Create a new label by clicking the button below')">
        <template #cell-name="{ row }">
            <button v-if="can('Label Update')" type="button" class="font-medium text-brand-600 dark:text-brand-400 hover:underline" @click="editRow(row)">{{ row.name }}</button>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-color="{ row }">
            <ColorBadge :text="row.name" :color="row.text_color" :background="row.background_color" />
        </template>
        <template #cell-locales="{ row }">
            <span class="text-muted uppercase">{{ row.locales.join(', ') }}</span>
        </template>
        <template #empty>
            <Button v-if="can('Label Create')" variant="primary" @click="add">{{ t('Add Label') }}</Button>
        </template>
        <template #actions="{ row }">
            <RecordActions
                entity="Label"
                routes="labels"
                :id="row.id"
                :title="row.name"
                :fields="[
                    { label: t('Name'), value: row.name },
                    { label: t('Slug'), value: row.slug },
                    { label: t('Description'), value: row.description },
                ]"
                @edit="editRow(row)"
                @delete="remove(row.id, row.name)"
            />
        </template>
        <template #bulk="{ selected }">
            <Button variant="danger" size="sm" @click="removeMany(selected)">{{ t('Delete') }}</Button>
        </template>
    </DataTable>

    <Modal v-model:open="open" :title="editingId ? t('Edit Label') : t('Add Label')" width="max-w-md">
        <form id="label-form" @submit.prevent="submit">
            <BadgeFields :form="form" />
        </form>
        <template #footer>
            <Button @click="open = false">{{ t('Cancel') }}</Button>
            <Button variant="primary" :loading="form.processing" @click="submit">{{ t('Save') }}</Button>
        </template>
    </Modal>
</template>
