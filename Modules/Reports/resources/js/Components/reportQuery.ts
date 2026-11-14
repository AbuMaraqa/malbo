/**
 * The report filters live in the URL as `filter[key]`, like a DataTable's, so the table keeps
 * them when it pages or sorts. These helpers read and change them.
 */
export type FilterValues = Record<string, string>;

export function currentFilters(search: string): FilterValues {
    const values: FilterValues = {};

    new URLSearchParams(search).forEach((value, key) => {
        const match = /^filter\[(\w+)]$/.exec(key);

        if (match && value !== '') {
            values[match[1]] = value;
        }
    });

    return values;
}

/**
 * The filters after `changes`: an empty or null value removes its key; choosing a preset forgets
 * the explicit dates (they would win over it on the server).
 */
export function applyFilters(current: FilterValues, changes: Record<string, string | number | null | undefined>): FilterValues {
    const next: FilterValues = { ...current };

    if (changes.preset !== undefined && changes.preset !== null && changes.preset !== 'custom') {
        delete next.date_from;
        delete next.date_to;
    }

    for (const [key, value] of Object.entries(changes)) {
        if (value === null || value === undefined || value === '') {
            delete next[key];
        } else {
            next[key] = String(value);
        }
    }

    return next;
}

/** "+4.2%", "-10%"; null when there is nothing to compare with. */
export function formatChange(value: number | null | undefined): string | null {
    if (value === null || value === undefined) return null;

    return `${value > 0 ? '+' : ''}${value.toLocaleString('en', { maximumFractionDigits: 1 })}%`;
}
