export interface Item {
    id: number;
    name: string;
    sku: string | null;
    active: boolean;
    wholesale_price: number | null;
    stock_quantity: number;
    manage_stock: boolean;
    incoming_quantity: number;
    incoming_expected_at: string | null;
}

export interface Tier {
    id: number;
    name: string;
    discount_percent: number;
    is_default: boolean;
}

export interface PriceForm {
    by_piece: boolean;
    /** item id => tier id => price ('' for none) */
    prices: Record<string, Record<string, number | string>>;
    breaks: { min_quantity: number | string; discount_percent: number | string }[];
}
