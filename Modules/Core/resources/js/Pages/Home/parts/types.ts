export interface Metric {
    key: 'users' | 'clients' | 'products' | 'orders';
    title: string;
    value: number;
}

export interface QuickLink {
    key: 'branches' | 'products' | 'categories';
    label: string;
    /** Null while the page is not available yet. */
    url: string | null;
}

export interface MonthlySeries {
    categories: string[];
    data: number[];
}

export interface LabelledSeries {
    labels: string[];
    data: number[];
}

export interface RecentOrder {
    id: number;
    order_number: string;
    customer: string | null;
    total: string;
    status: string | null;
    tone: 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand';
    date: string | null;
    url: string | null;
}

export interface RecentOrders {
    url: string | null;
    rows: RecentOrder[];
}
