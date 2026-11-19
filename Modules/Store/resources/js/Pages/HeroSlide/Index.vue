<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

import Toggle from '@/Components/form/Toggle.vue';
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
    title: string | null;
    subtitle: string | null;
    link: string | null;
    sort_order: number;
    is_active: boolean;
    live: boolean;
    starts_at: string | null;
    ends_at: string | null;
}

defineProps<{ table: TablePayload<Row>; previewUrl: string }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'slide', label: t('Slide') },
    { key: 'sort_order', label: t('Sort Order'), sort: 'sort_order', class: 'tabular' },
    { key: 'dates', label: t('Dates') },
    { key: 'live', label: t('Status') },
    { key: 'is_active', label: t('Shown in the store') },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'boolean', key: 'is_active', label: t('Shown in the store'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'boolean', key: 'live', label: t('Live now'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'date-range', key: 'starts_at', label: t('Starts at') },
    { type: 'date-range', key: 'ends_at', label: t('Ends at') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

function toggle(row: Row, value: boolean | undefined): void {
    router.put(route('hero-slides.status', row.id), { active: !!value }, { preserveScroll: true, only: ['table', 'flash'] });
}

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete this slide?'), description: t('It leaves the home page slider with its pictures.') })) {
        router.delete(route('hero-slides.destroy', row.id), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader
        :title="t('Home slider')"
        :description="t('The slides at the top of the home page, in their order. Without any, the store shows its own banner and picks.')"
    >
        <template #actions>
            <Button :href="previewUrl" external>{{ t('View the store') }}</Button>
            <Button v-if="can('Hero Slide Create')" variant="primary" :href="route('hero-slides.add')">{{ t('Add slide') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No slides yet')" :empty-text="t('Add one, or change the filters.')">
        <template #cell-slide="{ row }">
            <div class="flex items-center gap-3">
                <img v-if="row.image" :src="row.image" alt="" class="h-12 w-24 shrink-0 rounded-lg object-cover" />
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-ink">{{ row.title ?? t('Picture only') }}</p>
                    <p v-if="row.link" dir="ltr" class="truncate text-sm text-muted ltr:text-start rtl:text-end">{{ row.link }}</p>
                </div>
            </div>
        </template>
        <template #cell-dates="{ row }">
            <span v-if="row.starts_at || row.ends_at" class="text-sm whitespace-nowrap tabular-nums">{{ row.starts_at ?? '…' }} — {{ row.ends_at ?? '…' }}</span>
            <span v-else class="text-sm text-muted">{{ t('Always') }}</span>
        </template>
        <template #cell-live="{ row }">
            <Badge :tone="row.live ? 'success' : row.is_active ? 'warning' : 'neutral'">
                {{ row.live ? t('Live now') : row.is_active ? t('Outside its dates') : t('Hidden') }}
            </Badge>
        </template>
        <template #cell-is_active="{ row }">
            <Toggle :model-value="row.is_active" :disabled="!can('Hero Slide Update')" :aria-label="t('Shown in the store')" @update:model-value="toggle(row, $event)" />
        </template>
        <template #actions="{ row }">
            <RowMenu>
                <DropdownItem v-if="can('Hero Slide Update')" :href="route('hero-slides.edit', row.id)"><Pencil />{{ t('Edit') }}</DropdownItem>
                <DropdownItem v-if="row.link" :href="row.link" external><ExternalLink />{{ t('Open the link') }}</DropdownItem>
                <DropdownSeparator v-if="can('Hero Slide Delete')" />
                <DropdownItem v-if="can('Hero Slide Delete')" destructive @select="remove(row)"><Trash2 />{{ t('Delete') }}</DropdownItem>
            </RowMenu>
        </template>
    </DataTable>
</template>
