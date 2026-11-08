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
import { Pencil, Trash2 } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    title: string;
    slug: string;
    url: string;
    is_system: boolean;
    is_published: boolean;
    show_in_footer: boolean;
    sort_order: number;
    created_at: string | null;
}

defineProps<{ table: TablePayload<Row> }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'title', label: t('Title'), sort: 'title' },
    { key: 'slug', label: t('Link'), sort: 'slug' },
    { key: 'type', label: t('Type') },
    { key: 'is_published', label: t('Published') },
    { key: 'show_in_footer', label: t('In footer') },
    { key: 'sort_order', label: t('Sort Order'), sort: 'sort_order', class: 'tabular' },
    { key: 'created_at', label: t('Created at'), sort: 'created_at', toggleable: true, hidden: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'boolean', key: 'is_published', label: t('Published'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'boolean', key: 'show_in_footer', label: t('In footer'), trueLabel: t('Yes'), falseLabel: t('No') },
    { key: 'type', label: t('Type'), options: [{ value: 'system', label: t('Store policy') }, { value: 'custom', label: t('Custom page') }] },
    { type: 'number-range', key: 'sort_order', label: t('Sort Order') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

function toggle(row: Row, field: 'published' | 'footer', value: boolean | undefined): void {
    router.put(route(`pages.${field}`, row.id), { value: !!value }, { preserveScroll: true, only: ['table', 'flash'] });
}

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.title }), description: t('This cannot be undone.') })) {
        router.delete(route('pages.destroy', row.id), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader
        :title="t('Store pages')"
        :description="t('Policies and information pages shown in your store footer. The starter texts are general: review them so they match how you work.')"
    >
        <template #actions>
            <Button v-if="can('Page Create')" variant="primary" :href="route('pages.add')">{{ t('Add page') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No pages found')" :empty-text="t('Add a page, or change the filters.')">
        <template #cell-title="{ row }">
            <Link v-if="can('Page Update')" :href="route('pages.edit', row.id)" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ row.title }}</Link>
            <template v-else>{{ row.title }}</template>
        </template>
        <template #cell-slug="{ row }">
            <a :href="row.url" target="_blank" rel="noopener" dir="ltr" class="text-muted hover:underline">/page/{{ row.slug }}</a>
        </template>
        <template #cell-type="{ row }">
            <Badge :tone="row.is_system ? 'neutral' : 'info'">{{ row.is_system ? t('Store policy') : t('Custom page') }}</Badge>
        </template>
        <template #cell-is_published="{ row }">
            <Toggle :model-value="row.is_published" :disabled="!can('Page Update')" :aria-label="t('Published')" @update:model-value="toggle(row, 'published', $event)" />
        </template>
        <template #cell-show_in_footer="{ row }">
            <Toggle :model-value="row.show_in_footer" :disabled="!can('Page Update')" :aria-label="t('In footer')" @update:model-value="toggle(row, 'footer', $event)" />
        </template>
        <template #actions="{ row }">
            <Button v-if="can('Page Update')" variant="ghost" size="icon" :href="route('pages.edit', row.id)" :aria-label="t('Edit')"><Pencil class="size-4" /></Button>
            <Button v-if="can('Page Delete') && !row.is_system" variant="ghost" size="icon" :aria-label="t('Delete')" @click="remove(row)"><Trash2 class="size-4" /></Button>
        </template>
    </DataTable>
</template>
