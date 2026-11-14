<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import RowMenu from '@/Components/table/RowMenu.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import DropdownSeparator from '@/Components/ui/DropdownSeparator.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { ExternalLink, Pencil, Trash2 } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    image: string | null;
    name: string;
    type: string;
    type_label: string | null;
    components_count: number;
    price: string;
    percent: number | null;
    categories: string;
    published: boolean;
    is_active: boolean;
    url: string;
    created_at: string | null;
}

const props = defineProps<{ table: TablePayload<Row>; types: Option[]; statuses: Option[] }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'type', label: t('Type') },
    { key: 'components_count', label: t('Components'), class: 'tabular' },
    { key: 'price', label: t('Price'), sort: 'base_price', class: 'tabular' },
    { key: 'categories', label: t('Category'), toggleable: true },
    { key: 'status', label: t('Status') },
    { key: 'created_at', label: t('Created at'), sort: 'created_at', toggleable: true, hidden: true },
]);

const filters = computed<TableFilter[]>(() => [
    { key: 'bundle_type', label: t('Type'), options: props.types },
    { key: 'status', label: t('Status'), options: props.statuses },
    {
        key: 'is_active',
        label: t('Active'),
        options: [
            { value: 1, label: t('Active') },
            { value: 0, label: t('Inactive') },
        ],
    },
    { type: 'number-range', key: 'base_price', label: t('Price') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.name }), description: t('Its components stay; orders keep what was sold.') })) {
        router.delete(route('bundles.destroy', row.id), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Sets and bundles')" :description="t('Outfits and offers made of your products. They have no stock of their own: what can be sold comes from their components.')">
        <template #actions>
            <Button v-if="can('Bundle Create')" variant="primary" :href="route('bundles.add')">{{ t('Add set or bundle') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No sets or bundles yet')" :empty-text="t('Add one, or change the filters.')">
        <template #cell-name="{ row }">
            <div class="flex items-center gap-3">
                <img v-if="row.image" :src="row.image" alt="" class="size-10 rounded-lg object-cover" />
                <Link v-if="can('Bundle Update')" :href="route('bundles.edit', row.id)" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ row.name }}</Link>
                <span v-else class="font-medium">{{ row.name }}</span>
            </div>
        </template>
        <template #cell-type="{ row }">
            <Badge :tone="row.type === 'set' ? 'info' : 'neutral'">{{ row.type_label }}</Badge>
        </template>
        <template #cell-price="{ row }">
            {{ row.price }}
            <span v-if="row.percent !== null" class="text-xs text-muted">({{ t(':percent% off', { percent: row.percent }) }})</span>
        </template>
        <template #cell-status="{ row }">
            <Badge :tone="row.published && row.is_active ? 'success' : 'neutral'">{{ row.published ? (row.is_active ? t('Published') : t('Inactive')) : t('Draft') }}</Badge>
        </template>
        <template #actions="{ row }">
            <RowMenu>
                <DropdownItem v-if="can('Bundle Update')" :href="route('bundles.edit', row.id)"><Pencil />{{ t('Edit') }}</DropdownItem>
                <DropdownItem :href="row.url" external><ExternalLink />{{ t('View in the store') }}</DropdownItem>
                <DropdownSeparator v-if="can('Bundle Delete')" />
                <DropdownItem v-if="can('Bundle Delete')" destructive @select="remove(row)"><Trash2 />{{ t('Delete') }}</DropdownItem>
            </RowMenu>
        </template>
    </DataTable>
</template>
