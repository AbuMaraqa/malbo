<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import MetricCard from '@/tailadmin/dashboard/MetricCard.vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { confirm } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { Boxes, CircleAlert, CircleCheck, ExternalLink, Plus } from '@/lib/icons';
import type { FilterOption, TableColumn, TableFilter, TablePayload } from '@/types/table';

interface StoreRow {
    id: string;
    name: string;
    domain: string | null;
    owner_name: string | null;
    owner_email: string | null;
    owner_phone: string | null;
    business_type: string | null;
    status: string;
    is_active: boolean;
    created_at: string | null;
    store_url: string;
    imported: boolean;
}

const props = defineProps<{
    table: TablePayload<StoreRow>;
    counts: { total: number; active: number; suspended: number; createdThisMonth: number };
    businessTypes: FilterOption[];
    storesUrl: string;
}>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Store') },
    { key: 'owner_name', label: t('Owner') },
    { key: 'status', label: t('Status'), sort: 'status' },
    { key: 'business_type', label: t('What do you sell?'), toggleable: true, hidden: true },
    { key: 'created_at', label: t('Created'), sort: 'created_at', toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { key: 'status', label: t('Status'), options: [{ value: 'active', label: t('Active') }, { value: 'suspended', label: t('Suspended') }] },
    { key: 'business_type', label: t('What do you sell?'), options: props.businessTypes },
    { type: 'date-range', key: 'created_at', label: t('Created') },
]);

const metrics = computed(() => [
    { key: 'total', title: t('All stores'), value: props.counts.total, icon: Boxes },
    { key: 'active', title: t('Active'), value: props.counts.active, icon: CircleCheck },
    { key: 'suspended', title: t('Suspended'), value: props.counts.suspended, icon: CircleAlert },
    { key: 'createdThisMonth', title: t('New this month'), value: props.counts.createdThisMonth, icon: Plus },
]);

function businessLabel(value: string | null): string {
    return props.businessTypes.find((type) => type.value === value)?.label ?? '—';
}

function contact(row: StoreRow): string {
    return [row.owner_email, row.owner_phone].filter(Boolean).join(' · ');
}

function openAdmin(row: StoreRow): void {
    router.post(`${props.storesUrl}/${row.id}/open-admin`, {}, { preserveScroll: true });
}

async function suspend(row: StoreRow): Promise<void> {
    const confirmed = await confirm({
        title: t('Suspend :store?', { store: row.name }),
        description: t('Buyers and staff see a "store unavailable" page until you activate it again. No data is deleted.'),
        confirmLabel: t('Suspend'),
    });

    if (confirmed) {
        setStatus(row, 'suspended');
    }
}

function setStatus(row: StoreRow, status: string): void {
    router.patch(`${props.storesUrl}/${row.id}/status`, { status }, { preserveScroll: true });
}

async function remove(row: StoreRow): Promise<void> {
    const confirmed = await confirm({
        title: t('Delete :store?', { store: row.name }),
        description: row.imported
            ? t('The store is removed from the platform. It was imported, so its database is kept.')
            : t('The store, its database and its files are deleted. This cannot be undone.'),
        confirmLabel: t('Delete store'),
        typeToConfirm: row.id,
    });

    if (confirmed) {
        router.delete(`${props.storesUrl}/${row.id}`, { data: { confirm: row.id }, preserveScroll: true });
    }
}
</script>

<template>
    <PlatformLayout admin wide>
        <Head :title="t('Stores')" />
        <div class="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
            <h1 class="mb-6 text-xl font-semibold text-gray-800">{{ t('Stores') }}</h1>

            <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <MetricCard v-for="metric in metrics" :key="metric.key" :title="metric.title" :value="metric.value">
                    <template #icon><component :is="metric.icon" /></template>
                </MetricCard>
            </div>

            <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No stores yet')" :empty-text="t('Create one with php artisan stores:create.')">
                <template #cell-name="{ row }">
                    <p class="font-medium">{{ row.name }}</p>
                    <p class="text-xs text-muted" dir="ltr">{{ row.domain }}</p>
                </template>
                <template #cell-owner_name="{ row }">
                    <p>{{ row.owner_name ?? '—' }}</p>
                    <p class="text-xs text-muted" dir="ltr">{{ contact(row) }}</p>
                </template>
                <template #cell-status="{ row }">
                    <Badge :tone="row.is_active ? 'success' : 'danger'">{{ row.is_active ? t('Active') : t('Suspended') }}</Badge>
                </template>
                <template #cell-business_type="{ row }">{{ businessLabel(row.business_type) }}</template>
                <template #actions="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <a :href="row.store_url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-2 text-sm text-brand-600 hover:underline dark:text-brand-400">
                            {{ t('Open store') }}<ExternalLink class="size-3.5" />
                        </a>
                        <!-- Suspended stores answer 503 before the sign-in link is read. -->
                        <Button size="sm" variant="ghost" :disabled="!row.is_active" @click="openAdmin(row)">{{ t('Open admin') }}</Button>
                        <Button v-if="row.is_active" size="sm" variant="ghost" @click="suspend(row)">{{ t('Suspend') }}</Button>
                        <Button v-else size="sm" variant="ghost" @click="setStatus(row, 'active')">{{ t('Activate') }}</Button>
                        <Button size="sm" variant="danger" @click="remove(row)">{{ t('Delete') }}</Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </PlatformLayout>
</template>
