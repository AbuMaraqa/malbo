<script setup lang="ts" generic="Row extends { id: number | string }">
import { ArrowDown, ArrowUp, ArrowUpDown, Search } from '@/lib/icons';
import { computed, ref, toRef, watch } from 'vue';

import { t } from '@/lib/i18n';
import { useDataTable } from '@/lib/useDataTable';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import TableColumnsMenu from './TableColumnsMenu.vue';
import TableEmpty from './TableEmpty.vue';
import TableFilterChips from './TableFilterChips.vue';
import TableFiltersMenu from './TableFiltersMenu.vue';
import TablePagination from './TablePagination.vue';

const props = withDefaults(
    defineProps<{
        table: TablePayload<Row>;
        columns: TableColumn[];
        /** Every admin table declares its filters (see tests/Unit/Architecture/TableFiltersTest). */
        filters: TableFilter[];
        heading?: string;
        searchable?: boolean;
        selectable?: boolean;
        emptyTitle?: string;
        emptyText?: string;
        /** Props to reload; other page props stay untouched. */
        only?: string[];
    }>(),
    { searchable: true, selectable: false, only: () => ['table', 'flash'] },
);

const state = useDataTable(toRef(props, 'table'), toRef(props, 'filters'), toRef(props, 'columns'), toRef(props, 'only'));
const { loading, values, search, visibleColumns, toggleableColumns, hidden, chips, visit, setFilter, removeFilter, resetFilters, toggleSort, toggleColumn } = state;

const selected = ref<(number | string)[]>([]);
const sort = computed(() => props.table.query.sort);
const allSelected = computed(() => props.table.data.length > 0 && props.table.data.every((row) => selected.value.includes(row.id)));
const isFiltered = computed(() => chips.value.length > 0 || !!search.value);

function toggleAll(): void {
    selected.value = allSelected.value ? [] : props.table.data.map((row) => row.id);
}

function toggleRow(id: number | string): void {
    selected.value = selected.value.includes(id) ? selected.value.filter((item) => item !== id) : [...selected.value, id];
}

function ariaSort(column: TableColumn): 'ascending' | 'descending' | undefined {
    if (!column.sort || sort.value.replace('-', '') !== column.sort) return undefined;

    return sort.value.startsWith('-') ? 'descending' : 'ascending';
}

watch(
    () => props.table.data,
    () => {
        const visible = props.table.data.map((row) => row.id);
        selected.value = selected.value.filter((id) => visible.includes(id));
    },
);

defineExpose({ clearSelection: () => (selected.value = []) });
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-line bg-card">
        <div class="flex flex-wrap items-center gap-2 border-b border-line px-4 py-3">
            <h2 v-if="heading" class="me-auto text-sm font-semibold">{{ heading }}</h2>

            <div v-if="selectable && selected.length" class="flex items-center gap-2 text-sm" :class="{ 'me-auto': !heading }">
                <span class="text-muted">{{ t(':count selected', { count: selected.length }) }}</span>
                <slot name="bulk" :selected="selected" />
            </div>

            <label v-if="searchable" class="relative min-w-44 flex-1 sm:max-w-64" :class="{ 'ms-auto': !heading && !selected.length }">
                <span class="sr-only">{{ t('Search...') }}</span>
                <Search class="pointer-events-none absolute start-2.5 top-2.5 size-4 text-muted" />
                <input v-model="search" type="search" :placeholder="t('Search...')" class="h-9 w-full rounded-control border border-line bg-card ps-8 pe-3 text-sm outline-none focus:border-brand" />
            </label>

            <TableFiltersMenu v-if="filters.length" :filters="filters" :values="values" :active-count="chips.length" @change="setFilter" @reset="resetFilters" />
            <TableColumnsMenu v-if="toggleableColumns.length" :columns="toggleableColumns" :hidden="hidden" @toggle="toggleColumn" />
        </div>

        <TableFilterChips :chips="chips" @remove="removeFilter" @reset="resetFilters" />

        <div class="overflow-x-auto transition-opacity" :class="{ 'opacity-50': loading }" :aria-busy="loading">
            <table class="w-full border-collapse text-sm">
                <thead class="bg-surface/60">
                    <tr class="border-b border-line text-xs text-muted">
                        <th v-if="selectable" class="w-10 px-4 py-3 text-start">
                            <input type="checkbox" :checked="allSelected" :aria-label="t('Select all')" class="accent-brand" @change="toggleAll" />
                        </th>
                        <th v-for="column in visibleColumns" :key="column.key" class="px-4 py-3 text-start font-medium whitespace-nowrap" :class="column.class" :aria-sort="ariaSort(column)">
                            <button v-if="column.sort" type="button" class="group inline-flex items-center gap-1 hover:text-ink" @click="toggleSort(column)">
                                {{ column.label }}
                                <ArrowUp v-if="sort === column.sort" class="size-3 text-ink" />
                                <ArrowDown v-else-if="sort === `-${column.sort}`" class="size-3 text-ink" />
                                <ArrowUpDown v-else class="size-3 opacity-40 group-hover:opacity-100" />
                            </button>
                            <template v-else>{{ column.label }}</template>
                        </th>
                        <th v-if="$slots.actions" class="px-4 py-3"><span class="sr-only">{{ t('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in table.data" :key="row.id" class="border-b border-line last:border-b-0 hover:bg-surface/50" :class="{ 'bg-brand-50/40 dark:bg-brand-500/10': selected.includes(row.id) }">
                        <td v-if="selectable" class="px-4 py-2.5">
                            <input type="checkbox" :checked="selected.includes(row.id)" :aria-label="t('Select row')" class="accent-brand" @change="toggleRow(row.id)" />
                        </td>
                        <td v-for="column in visibleColumns" :key="column.key" class="px-4 py-2.5" :class="column.class">
                            <slot :name="`cell-${column.key}`" :row="row">{{ (row as Record<string, unknown>)[column.key] }}</slot>
                        </td>
                        <td v-if="$slots.actions" class="px-4 py-1.5 text-end whitespace-nowrap"><slot name="actions" :row="row" /></td>
                    </tr>
                </tbody>
            </table>

            <TableEmpty v-if="table.data.length === 0" :title="emptyTitle" :text="emptyText" :filtered="isFiltered" @reset="resetFilters(); search = ''">
                <slot v-if="$slots.empty" name="empty" />
            </TableEmpty>
        </div>

        <TablePagination v-if="table.meta.total > 0" :meta="table.meta" @page="(page) => visit({ page })" @per-page="(perPage) => visit({ perPage, page: 1 })" />
    </div>
</template>
