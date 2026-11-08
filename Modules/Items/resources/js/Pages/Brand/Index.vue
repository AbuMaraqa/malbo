<script setup lang="ts">
import { computed, ref } from 'vue';

import type { ExistingImage } from '@/Components/form/ImageUpload.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import RecordActions from '../parts/RecordActions.vue';
import { useRecordDelete } from '../parts/useRecordDelete';
import { useRecordModal } from '../parts/useRecordModal';
import BrandFields, { type BrandForm } from './BrandFields.vue';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    image: string | null;
    image_id: number | null;
    name: string;
    slug: string;
    description: string | null;
    locales: string[];
    form: { name: string; description: string };
}

defineProps<{ table: TablePayload<Row> }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'image', label: t('Image') },
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'slug', label: t('Slug'), sort: 'slug' },
    { key: 'locales', label: t('Locale'), toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'name', label: t('Name'), placeholder: t('Search by name') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

const blank = (): BrandForm => ({ name: '', slug: '', description: '', image: null, removed_image: [] });

const { open, editingId, form, add: addBrand, edit, submit } = useRecordModal<BrandForm>('brands', blank, true);
const { remove, removeMany } = useRecordDelete('brands');

const images = ref<ExistingImage[]>([]);

function add(): void {
    images.value = [];
    addBrand();
}

function editRow(row: Row): void {
    images.value = row.image && row.image_id ? [{ id: row.image_id, url: row.image }] : [];
    edit(row.id, { ...blank(), ...row.form, slug: row.slug });
}
</script>

<template>
    <PageHeader :title="t('Brands List')">
        <template #actions>
            <Button v-if="can('Brand Create')" variant="primary" @click="add">{{ t('Add Brand') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :selectable="can('Brand Delete')" :empty-title="t('No brands found')" :empty-text="t('Create a new brand by clicking the button below')">
        <template #cell-image="{ row }">
            <img v-if="row.image" :src="row.image" alt="" class="size-12 rounded-control object-cover" />
        </template>
        <template #cell-name="{ row }">
            <button v-if="can('Brand Update')" type="button" class="font-medium text-brand-600 dark:text-brand-400 hover:underline" @click="editRow(row)">{{ row.name }}</button>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-locales="{ row }">
            <span class="text-muted uppercase">{{ row.locales.join(', ') }}</span>
        </template>
        <template #empty>
            <Button v-if="can('Brand Create')" variant="primary" @click="add">{{ t('Add Brand') }}</Button>
        </template>
        <template #actions="{ row }">
            <RecordActions
                entity="Brand"
                routes="brands"
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

    <Modal v-model:open="open" :title="editingId ? t('Edit Brand') : t('Add Brand')" width="max-w-md">
        <form @submit.prevent="submit">
            <BrandFields :form="form" :images="images" :image-required="!editingId" />
        </form>
        <template #footer>
            <Button @click="open = false">{{ t('Cancel') }}</Button>
            <Button variant="primary" :loading="form.processing" @click="submit">{{ t('Save') }}</Button>
        </template>
    </Modal>
</template>
