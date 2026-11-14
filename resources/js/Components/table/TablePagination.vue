<script setup lang="ts">
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { ChevronLeft, ChevronRight } from '@/lib/icons';
import type { TableMeta } from '@/types/table';

const props = defineProps<{ meta: TableMeta }>();

const emit = defineEmits<{ page: [page: number]; perPage: [size: number] }>();

/** The first and last page, and two on each side of the current one; gaps become "…". */
const pages = computed<(number | null)[]>(() => {
    const { current_page: current, last_page: last } = props.meta;
    const wanted = new Set([1, last, current - 2, current - 1, current, current + 1, current + 2].filter((page) => page >= 1 && page <= last));
    const sorted = [...wanted].sort((a, b) => a - b);

    return sorted.flatMap((page, index) => (index > 0 && page - sorted[index - 1] > 1 ? [null, page] : [page]));
});

const button = 'inline-flex size-8 items-center justify-center rounded-lg text-sm font-medium transition-colors disabled:pointer-events-none disabled:opacity-40';
</script>

<template>
    <nav class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3.5 text-sm text-muted" :aria-label="t('Pagination')">
        <label class="flex items-center gap-2">
            <span>{{ t('Show') }}</span>
            <select :value="meta.per_page" class="h-8 rounded-lg border border-line bg-card px-2 text-sm text-ink shadow-theme-xs" @change="emit('perPage', Number(($event.target as HTMLSelectElement).value))">
                <option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
            </select>
            <span>{{ t('per page') }}</span>
        </label>
        <div class="flex items-center gap-1">
            <p class="tabular me-3">{{ t(':from to :to of :total', { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total }) }}</p>
            <button type="button" :class="[button, 'text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5']" :disabled="meta.current_page <= 1" :aria-label="t('Previous page')" @click="emit('page', meta.current_page - 1)">
                <ChevronRight class="size-4 ltr:rotate-180" />
            </button>
            <template v-for="(page, index) in pages" :key="page ?? `gap-${index}`">
                <span v-if="page === null" class="px-1 text-gray-400" aria-hidden="true">…</span>
                <button
                    v-else
                    type="button"
                    class="tabular"
                    :class="[button, page === meta.current_page ? 'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white']"
                    :aria-current="page === meta.current_page ? 'page' : undefined"
                    :aria-label="t('Page :page', { page })"
                    @click="page !== meta.current_page && emit('page', page)"
                >
                    {{ page }}
                </button>
            </template>
            <button type="button" :class="[button, 'text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5']" :disabled="meta.current_page >= meta.last_page" :aria-label="t('Next page')" @click="emit('page', meta.current_page + 1)">
                <ChevronLeft class="size-4 ltr:rotate-180" />
            </button>
        </div>
    </nav>
</template>
