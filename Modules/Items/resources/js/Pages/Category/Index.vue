<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import Toggle from '@/Components/form/Toggle.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';
import type { FilterOption, TableColumn, TableFilter, TablePayload } from '@/types/table';

import RecordActions from '../parts/RecordActions.vue';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    image: string;
    name: string;
    slug: string;
    description: string | null;
    parent: string | null;
    sort_order: number;
    active: boolean;
    locales: string[];
}

const props = defineProps<{ table: TablePayload<Row>; parents: FilterOption[] }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'image', label: t('Image') },
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'slug', label: t('Slug'), sort: 'slug' },
    { key: 'parent', label: t('Parent Category'), sort: 'parent_id', toggleable: true },
    { key: 'sort_order', label: t('Sort Order'), sort: 'sort_order', class: 'tabular', toggleable: true },
    { key: 'active', label: t('Active'), sort: 'status' },
    { key: 'locales', label: t('Locale'), toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'name', label: t('Name'), placeholder: t('Search by name') },
    { key: 'status', label: t('Status'), options: [{ value: 1, label: t('Active') }, { value: 0, label: t('Inactive') }] },
    { key: 'parent_id', label: t('Parent Category'), options: props.parents },
    { type: 'number-range', key: 'sort_order', label: t('Sort Order') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

function setActive(row: Row, active: boolean): void {
    router.put(route('categories.status', row.id), { active }, { preserveScroll: true });
}

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.name }), description: t('This cannot be undone.') })) {
        router.delete(route('categories.destroy', row.id), { preserveScroll: true });
    }
}

async function removeMany(ids: (number | string)[]): Promise<void> {
    if (await confirm({ title: t('Delete :count items?', { count: ids.length }), description: t('This cannot be undone.') })) {
        router.delete(route('categories.destroy-many'), { data: { ids }, preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Categories List')">
        <template #actions>
            <Button v-if="can('Category Create')" variant="primary" :href="route('categories.add')">{{ t('Add Category') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :selectable="can('Category Delete')" :empty-title="t('No categories found')" :empty-text="t('Add the categories your products belong to.')">
        <template #cell-image="{ row }">
            <img v-if="row.image" :src="row.image" alt="" class="size-12 rounded-control object-cover" />
        </template>
        <template #cell-name="{ row }">
            <Link v-if="can('Category Update')" :href="route('categories.edit', row.id)" class="font-medium text-brand-600 dark:text-brand-400 hover:underline">{{ row.name }}</Link>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-parent="{ row }">{{ row.parent ?? t('Parent') }}</template>
        <template #cell-active="{ row }">
            <Toggle v-if="can('Category Update')" :model-value="row.active" :aria-label="t('Active')" @update:model-value="setActive(row, !!$event)" />
            <Badge v-else :tone="row.active ? 'success' : 'neutral'">{{ row.active ? t('Active') : t('Inactive') }}</Badge>
        </template>
        <template #cell-locales="{ row }">
            <span class="text-muted uppercase">{{ row.locales.join(', ') }}</span>
        </template>
        <template #actions="{ row }">
            <RecordActions
                entity="Category"
                routes="categories"
                :id="row.id"
                :title="row.name"
                :edit-href="route('categories.edit', row.id)"
                :fields="[
                    { label: t('Name'), value: row.name },
                    { label: t('Slug'), value: row.slug },
                    { label: t('Description'), value: row.description },
                ]"
                @delete="remove(row)"
            />
        </template>
        <template #bulk="{ selected }">
            <Button variant="danger" size="sm" @click="removeMany(selected)">{{ t('Delete') }}</Button>
        </template>
    </DataTable>
</template>
