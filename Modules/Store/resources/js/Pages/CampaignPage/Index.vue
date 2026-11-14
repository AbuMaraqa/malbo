<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

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
    title: string;
    slug: string;
    url: string;
    status: 'draft' | 'published';
    live: boolean;
    starts_at: string | null;
    ends_at: string | null;
    blocks_count: number;
    created_at: string | null;
}

defineProps<{ table: TablePayload<Row> }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'title', label: t('Title'), sort: 'title' },
    { key: 'slug', label: t('Link'), sort: 'slug' },
    { key: 'status', label: t('Status') },
    { key: 'dates', label: t('Dates') },
    { key: 'blocks_count', label: t('Blocks'), class: 'tabular' },
    { key: 'created_at', label: t('Created at'), sort: 'created_at', toggleable: true, hidden: true },
]);

const filters = computed<TableFilter[]>(() => [
    {
        key: 'status',
        label: t('Status'),
        options: [
            { value: 'draft', label: t('Draft') },
            { value: 'published', label: t('Published') },
        ],
    },
    { type: 'boolean', key: 'live', label: t('Live now'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'date-range', key: 'starts_at', label: t('Starts at') },
    { type: 'date-range', key: 'ends_at', label: t('Ends at') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.title }), description: t('This cannot be undone.') })) {
        router.delete(route('campaign-pages.destroy', row.id), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Campaign pages')" :description="t('Landing pages for your offers, built from ready blocks. Each has its own link, and shows only while it is published and inside its dates.')">
        <template #actions>
            <Button v-if="can('Campaign Page Create')" variant="primary" :href="route('campaign-pages.add')">{{ t('Add campaign page') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No campaign pages yet')" :empty-text="t('Add one, or change the filters.')">
        <template #cell-title="{ row }">
            <Link v-if="can('Campaign Page Update')" :href="route('campaign-pages.edit', row.id)" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ row.title }}</Link>
            <template v-else>{{ row.title }}</template>
        </template>
        <template #cell-slug="{ row }">
            <a :href="row.url" target="_blank" rel="noopener" dir="ltr" class="text-muted hover:underline">/campaign/{{ row.slug }}</a>
        </template>
        <template #cell-status="{ row }">
            <Badge :tone="row.live ? 'success' : row.status === 'published' ? 'warning' : 'neutral'">
                {{ row.live ? t('Live now') : row.status === 'published' ? t('Outside its dates') : t('Draft') }}
            </Badge>
        </template>
        <template #cell-dates="{ row }">
            <span class="text-sm text-muted tabular-nums" dir="ltr">{{ row.starts_at ?? '—' }} → {{ row.ends_at ?? '—' }}</span>
        </template>
        <template #actions="{ row }">
            <RowMenu>
                <DropdownItem v-if="can('Campaign Page Update')" :href="route('campaign-pages.edit', row.id)"><Pencil />{{ t('Edit') }}</DropdownItem>
                <DropdownItem :href="row.url" external><ExternalLink />{{ row.live ? t('View page') : t('Preview') }}</DropdownItem>
                <DropdownSeparator v-if="can('Campaign Page Delete')" />
                <DropdownItem v-if="can('Campaign Page Delete')" destructive @select="remove(row)"><Trash2 />{{ t('Delete') }}</DropdownItem>
            </RowMenu>
        </template>
    </DataTable>
</template>
