export interface TableMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

/** The shape `Modules\Core\Support\AdminTable::rows()` sends. */
export interface TablePayload<Row> {
    data: Row[];
    meta: TableMeta;
    query: { filter: Record<string, string>; sort: string };
}

export interface TableColumn {
    key: string;
    label: string;
    /** The name the server sorts by; omit when the column cannot be sorted. */
    sort?: string;
    class?: string;
    /** The column can be hidden from the columns menu. Hidden by default when `hidden` is true. */
    toggleable?: boolean;
    hidden?: boolean;
}

export interface FilterOption {
    value: string | number;
    label: string;
}

/**
 * A table filter. The URL carries it as `filter[key]`; date and number ranges as
 * `filter[key_from]` and `filter[key_to]`.
 */
export type TableFilter =
    | { type?: 'select'; key: string; label: string; options: FilterOption[] }
    | { type: 'boolean'; key: string; label: string; trueLabel?: string; falseLabel?: string }
    | { type: 'text'; key: string; label: string; placeholder?: string }
    | { type: 'date-range'; key: string; label: string }
    | { type: 'number-range'; key: string; label: string };

export interface FilterChip {
    /** URL keys this chip clears. */
    keys: string[];
    label: string;
}
