<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import DataTable from '@/Components/table/DataTable.vue';
import RowMenu from '@/Components/table/RowMenu.vue';
import Badge from '@/Components/ui/Badge.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import { Check, Pencil, X } from '@/lib/icons';
import { route } from '@/lib/route';
import type { FilterOption, TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface ReviewRow {
    id: number;
    product: string;
    author: string;
    rating: number;
    title: string | null;
    body: string;
    status: 'pending' | 'approved' | 'rejected';
    status_label: string;
    photos: number;
    replied: boolean;
    created: string | null;
    created_at: string | null;
}

const props = defineProps<{ table: TablePayload<ReviewRow>; products: FilterOption[]; statuses: FilterOption[] }>();

const tones = { pending: 'warning', approved: 'success', rejected: 'danger' } as const;

const columns = computed<TableColumn[]>(() => [
    { key: 'status', label: t('Status') },
    { key: 'rating', label: t('Rating'), sort: 'rating' },
    { key: 'body', label: t('Review') },
    { key: 'product', label: t('Product') },
    { key: 'author', label: t('Customer') },
    { key: 'created_at', label: t('Received'), sort: 'created_at' },
]);

const filters = computed<TableFilter[]>(() => [
    { key: 'status', label: t('Status'), options: props.statuses },
    { key: 'rating', label: t('Rating'), options: [5, 4, 3, 2, 1].map((value) => ({ value, label: '★'.repeat(value) })) },
    { key: 'product', label: t('Product'), options: props.products },
    { type: 'boolean', key: 'has_photos', label: t('With photos'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'date-range', key: 'created_at', label: t('Received') },
]);

function setStatus(row: ReviewRow, status: 'approved' | 'rejected'): void {
    router.put(route('reviews.status', row.id), { status }, { preserveScroll: true, only: ['table', 'flash'] });
}
</script>

<template>
    <PageHeader :title="t('Reviews')" :description="t('Reviews from buyers whose order was delivered. Only approved reviews show on the product page.')" />

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No reviews yet')" :empty-text="t('Reviews buyers write on product pages appear here.')">
        <template #cell-status="{ row }">
            <Badge :tone="tones[row.status]" dot>{{ row.status_label }}</Badge>
        </template>
        <template #cell-rating="{ row }">
            <span class="whitespace-nowrap text-warning-500" :aria-label="t(':rating out of 5 stars', { rating: row.rating })">{{ '★'.repeat(row.rating) }}<span class="text-gray-300 dark:text-gray-600">{{ '★'.repeat(5 - row.rating) }}</span></span>
        </template>
        <template #cell-body="{ row }">
            <component :is="can('Review Update') ? Link : 'div'" :href="can('Review Update') ? route('reviews.edit', row.id) : undefined" class="block max-w-md">
                <p v-if="row.title" class="font-medium text-ink">{{ row.title }}</p>
                <p class="line-clamp-2 text-sm text-muted">{{ row.body }}</p>
            </component>
            <p v-if="row.photos || row.replied" class="mt-1 text-xs text-muted">
                <span v-if="row.photos">{{ t(':count photos', { count: row.photos }) }}</span>
                <span v-if="row.photos && row.replied"> · </span>
                <span v-if="row.replied">{{ t('Replied') }}</span>
            </p>
        </template>
        <template #cell-created_at="{ row }">
            <span class="whitespace-nowrap text-muted" :title="row.created_at ?? undefined">{{ row.created }}</span>
        </template>
        <template #actions="{ row }">
            <RowMenu v-if="can('Review Update')">
                <DropdownItem v-if="row.status !== 'approved'" @select="setStatus(row, 'approved')"><Check />{{ t('Approve') }}</DropdownItem>
                <DropdownItem v-if="row.status !== 'rejected'" @select="setStatus(row, 'rejected')"><X />{{ t('Reject') }}</DropdownItem>
                <DropdownItem @select="router.visit(route('reviews.edit', row.id))"><Pencil />{{ t('Reply and photos') }}</DropdownItem>
            </RowMenu>
        </template>
    </DataTable>
</template>
