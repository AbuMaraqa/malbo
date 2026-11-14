/** What every report page receives about its filters (Modules\Reports\Support\ReportFilters::toProps). */
export interface ReportFilterProps {
    preset: 'today' | '7d' | 'this_month' | 'last_month' | 'custom';
    date_from: string;
    date_to: string;
    channel: 'b2c' | 'b2b' | null;
    branch: number | null;
    category: number | null;
    interval: 'day' | 'month';
    by: string | null;
    timezone: string;
}

export interface ReportOption {
    value: string | number;
    label: string;
}

/** One figure of a report's summary row. */
export interface ReportKpi {
    key: string;
    label: string;
    value: string | number;
    /** Percent against the period before; null when there was nothing to compare. */
    change?: number | null;
    hint?: string;
}

/** A report's chart over its buckets. */
export interface BucketChart {
    keys: string[];
    categories: string[];
}
