<script setup lang="ts">
import { t } from '@/lib/i18n';

import { sizeRun } from './sizeRuns';
import type { GridRow } from './types';

/**
 * The size-run grid: a row per color, a cell per size with what is available now and its price.
 * By the piece, each cell takes any number; otherwise a row takes N full runs (one of each size that
 * can be ordered) and its cells only show the result. Each row says what a run holds, how many runs
 * the stock allows and which sizes are left out. The server checks both again.
 */
defineProps<{ rows: GridRow[]; sizes: (string | null)[]; byPiece: boolean; runs: Record<number, number> }>();

const quantities = defineModel<Record<number, number>>({ required: true });

const emit = defineEmits<{ runs: [key: number, count: number] }>();

function setCell(id: number, event: Event, max: number | null): void {
    const value = Math.max(0, parseInt((event.target as HTMLInputElement).value, 10) || 0);
    quantities.value = { ...quantities.value, [id]: max === null ? value : Math.min(value, max) };
}

function setRuns(key: number, event: Event): void {
    emit('runs', key, Math.max(0, parseInt((event.target as HTMLInputElement).value, 10) || 0));
}

const cellClass = 'h-10 w-16 rounded-md border border-line bg-white text-center tabular-nums focus:border-store focus:outline-none disabled:bg-(--sand-light) disabled:text-muted';

</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-line bg-white">
        <table class="w-full min-w-max text-sm">
            <thead>
                <tr class="border-b border-line bg-(--sand-light)">
                    <th scope="col" class="px-3 py-2 text-start font-semibold">{{ t('Color') }}</th>
                    <th v-for="(size, index) in sizes" :key="index" scope="col" class="px-3 py-2 text-center font-semibold">{{ size ?? t('Quantity') }}</th>
                    <th scope="col" class="px-3 py-2 text-center font-semibold">{{ byPiece ? t('One of each size') : t('Size runs') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.key" class="border-b border-line last:border-0">
                    <th scope="row" class="px-3 py-3 text-start font-semibold">
                        <span class="inline-flex items-center gap-2">
                            <span v-if="row.swatch" class="size-4 rounded-full border border-line" :style="{ backgroundColor: row.swatch }" aria-hidden="true"></span>
                            {{ row.name ?? '—' }}
                        </span>
                    </th>
                    <td v-for="(cell, index) in row.cells" :key="index" class="px-3 py-3 text-center align-top">
                        <template v-if="cell">
                            <input
                                type="number"
                                min="0"
                                :max="cell.available ?? undefined"
                                inputmode="numeric"
                                :value="quantities[cell.id] ?? 0"
                                :disabled="!cell.orderable || !byPiece"
                                :aria-label="t(':color, size :size', { color: row.name ?? '', size: cell.size ?? '' })"
                                :class="cellClass"
                                @change="setCell(cell.id, $event, cell.available)"
                            />
                            <p class="mt-1 text-xs" :class="cell.orderable ? 'text-muted' : 'text-(--danger)'">
                                <template v-if="!cell.orderable">{{ t('Unavailable') }}</template>
                                <template v-else-if="cell.available !== null">{{ t(':count available', { count: cell.available }) }}</template>
                                <template v-else>{{ t('Available') }}</template>
                            </p>
                            <p v-if="cell.unit_label" class="text-xs font-semibold">{{ cell.unit_label }}</p>
                            <p v-if="cell.incoming" class="text-xs text-(--ink-soft)">
                                {{ cell.incoming.label }}
                                <template v-if="cell.in_stock !== null && cell.in_stock > 0"><br />{{ t(':count in stock now', { count: cell.in_stock }) }}</template>
                            </p>
                        </template>
                        <span v-else class="text-muted">—</span>
                    </td>
                    <td class="max-w-56 px-3 py-3 text-center align-top">
                        <template v-if="sizeRun(row).sizes.length">
                            <input
                                type="number"
                                min="0"
                                :max="sizeRun(row).max ?? undefined"
                                inputmode="numeric"
                                :value="runs[row.key] ?? 0"
                                :aria-label="t('Size runs of :color', { color: row.name ?? '' })"
                                :aria-describedby="`run-${row.key}`"
                                :class="cellClass"
                                @change="setRuns(row.key, $event)"
                            />
                            <p :id="`run-${row.key}`" class="mt-1 text-xs text-muted">
                                {{ t('One run: :sizes', { sizes: sizeRun(row).sizes.join(' · ') }) }}
                                <template v-if="sizeRun(row).max !== null"><br />{{ t('Up to :count runs', { count: sizeRun(row).max ?? 0 }) }}</template>
                                <template v-if="sizeRun(row).leftOut.length"><br />{{ t('Left out (sold out): :sizes', { sizes: sizeRun(row).leftOut.join(' · ') }) }}</template>
                            </p>
                        </template>
                        <p v-else class="text-xs text-(--danger)">{{ t('Sold out in this color') }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
