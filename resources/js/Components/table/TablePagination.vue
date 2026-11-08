<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@/lib/icons';

import { t } from '@/lib/i18n';
import type { TableMeta } from '@/types/table';

defineProps<{ meta: TableMeta }>();

const emit = defineEmits<{ page: [page: number]; perPage: [size: number] }>();

const button = 'rounded-control border border-line bg-card p-1.5 hover:bg-surface disabled:opacity-40 disabled:pointer-events-none';
</script>

<template>
    <nav class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3 text-sm text-muted" :aria-label="t('Pagination')">
        <p class="tabular">{{ t(':from to :to of :total', { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total }) }}</p>
        <div class="flex items-center gap-2">
            <label class="flex items-center gap-1.5">
                <span>{{ t('Rows') }}</span>
                <select :value="meta.per_page" class="h-8 rounded-control border border-line bg-card px-1.5 text-sm" @change="emit('perPage', Number(($event.target as HTMLSelectElement).value))">
                    <option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
                </select>
            </label>
            <button type="button" :class="button" :disabled="meta.current_page <= 1" :aria-label="t('Previous page')" @click="emit('page', meta.current_page - 1)">
                <ChevronRight class="size-4 ltr:rotate-180" />
            </button>
            <span class="tabular">{{ meta.current_page }} / {{ meta.last_page }}</span>
            <button type="button" :class="button" :disabled="meta.current_page >= meta.last_page" :aria-label="t('Next page')" @click="emit('page', meta.current_page + 1)">
                <ChevronLeft class="size-4 ltr:rotate-180" />
            </button>
        </div>
    </nav>
</template>
