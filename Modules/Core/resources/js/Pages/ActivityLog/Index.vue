<script setup lang="ts">
import { computed } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import ActivityChanges, { type ActivityChange } from './ActivityChanges.vue';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    event: string;
    event_label: string;
    subject: string;
    subject_id: number | null;
    causer: string | null;
    created_at: string | null;
    changes: ActivityChange[];
}

const props = defineProps<{ table: TablePayload<Row>; subjectTypes: Option[]; events: Option[]; causers: Option[] }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'created_at', label: t('Date'), sort: 'created_at' },
    { key: 'causer', label: t('By') },
    { key: 'event', label: t('Action') },
    { key: 'subject', label: t('Record') },
    { key: 'changes', label: t('Changes') },
]);

const filters = computed<TableFilter[]>(() => [
    { key: 'subject_type', label: t('Record type'), options: props.subjectTypes },
    { key: 'causer_id', label: t('By'), options: props.causers },
    { key: 'event', label: t('Action'), options: props.events },
    { type: 'date-range', key: 'created_at', label: t('Date') },
]);

const tones: Record<string, 'success' | 'info' | 'danger' | 'neutral'> = { created: 'success', updated: 'info', deleted: 'danger' };
</script>

<template>
    <PageHeader :title="t('Activity log')" :description="t('Who changed which record, when, and what changed. Passwords and secrets are never shown.')" />

    <DataTable :table="table" :columns="columns" :filters="filters" :searchable="false" :empty-title="t('No activity')" :empty-text="t('Changes to records show here.')">
        <template #cell-created_at="{ row }">
            <span dir="ltr" class="whitespace-nowrap">{{ row.created_at }}</span>
        </template>
        <template #cell-causer="{ row }">
            {{ row.causer ?? t('System') }}
        </template>
        <template #cell-event="{ row }">
            <Badge :tone="tones[row.event] ?? 'neutral'">{{ row.event_label }}</Badge>
        </template>
        <template #cell-subject="{ row }">
            <span class="whitespace-nowrap">{{ row.subject }} <span v-if="row.subject_id" class="text-muted" dir="ltr">#{{ row.subject_id }}</span></span>
        </template>
        <template #cell-changes="{ row }">
            <ActivityChanges :changes="row.changes" />
        </template>
    </DataTable>
</template>
