import { router } from '@inertiajs/vue3';
import { computed, ref, watch, type Ref } from 'vue';

import type { FilterChip, TableColumn, TableFilter, TablePayload } from '@/types/table';

/** The URL state of a table: search, filters, sort, page size. Changes reload only the table. */
export function useDataTable<Row>(
    table: Ref<TablePayload<Row>>,
    filters: Ref<TableFilter[]>,
    columns: Ref<TableColumn[]>,
    only: Ref<string[]>,
) {
    const loading = ref(false);
    const values = ref<Record<string, string>>({ ...table.value.query.filter });
    const search = ref<string>(table.value.query.filter.search ?? '');

    const storageKey = () => `table-columns:${typeof window === 'undefined' ? '' : window.location.pathname.replace(/^\/[a-z]{2}\//, '/')}`;

    function readHidden(): string[] {
        const fallback = columns.value.filter((column) => column.hidden).map((column) => column.key);

        if (typeof window === 'undefined') return fallback;

        try {
            const saved = window.localStorage.getItem(storageKey());
            return saved ? (JSON.parse(saved) as string[]) : fallback;
        } catch {
            return fallback;
        }
    }

    const hidden = ref<string[]>(readHidden());
    const visibleColumns = computed(() => columns.value.filter((column) => !hidden.value.includes(column.key)));
    const toggleableColumns = computed(() => columns.value.filter((column) => column.toggleable));

    function toggleColumn(key: string): void {
        hidden.value = hidden.value.includes(key) ? hidden.value.filter((item) => item !== key) : [...hidden.value, key];

        try {
            window.localStorage.setItem(storageKey(), JSON.stringify(hidden.value));
        } catch {
            // The choice simply lasts until the next visit.
        }
    }

    function clean(): Record<string, string> {
        return Object.fromEntries(Object.entries(values.value).filter(([, value]) => value !== '' && value !== null && value !== undefined));
    }

    function visit(params: Record<string, unknown> = {}): void {
        const filter = clean();
        const { sort } = table.value.query;
        const { per_page: perPage } = table.value.meta;

        router.get(
            window.location.pathname,
            {
                ...(Object.keys(filter).length ? { filter } : {}),
                ...(sort ? { sort } : {}),
                ...(perPage !== 25 ? { perPage } : {}),
                ...params,
            },
            {
                only: only.value,
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => (loading.value = true),
                onFinish: () => (loading.value = false),
            },
        );
    }

    let timer: ReturnType<typeof setTimeout>;
    watch(search, (term) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            values.value = { ...values.value, search: term };
            visit({ page: 1 });
        }, 300);
    });

    function setFilter(key: string, value: string): void {
        values.value = { ...values.value, [key]: value };
        visit({ page: 1 });
    }

    function removeFilter(keys: string[]): void {
        const next = { ...values.value };
        keys.forEach((key) => delete next[key]);
        values.value = next;
        visit({ page: 1 });
    }

    function resetFilters(): void {
        values.value = search.value ? { search: search.value } : {};
        visit({ page: 1 });
    }

    function toggleSort(column: TableColumn): void {
        if (!column.sort) return;
        const { sort } = table.value.query;
        visit({ sort: sort === column.sort ? `-${column.sort}` : column.sort, page: 1 });
    }

    const chips = computed<FilterChip[]>(() =>
        filters.value.flatMap((filter): FilterChip[] => {
            if (filter.type === 'date-range' || filter.type === 'number-range') {
                const from = values.value[`${filter.key}_from`];
                const to = values.value[`${filter.key}_to`];

                return from || to ? [{ keys: [`${filter.key}_from`, `${filter.key}_to`], label: `${filter.label}: ${[from, to].filter(Boolean).join(' - ')}` }] : [];
            }

            const value = values.value[filter.key];

            if (value === undefined || value === '') return [];

            let text = value;
            if (filter.type === 'boolean') {
                text = value === '1' ? (filter.trueLabel ?? '✓') : (filter.falseLabel ?? '✕');
            } else if (filter.type !== 'text') {
                text = filter.options.find((option) => String(option.value) === value)?.label ?? value;
            }

            return [{ keys: [filter.key], label: `${filter.label}: ${text}` }];
        }),
    );

    return { loading, values, search, hidden, visibleColumns, toggleableColumns, chips, visit, setFilter, removeFilter, resetFilters, toggleSort, toggleColumn };
}
