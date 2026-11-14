/** The portal's links (Modules\Wholesale\Support\PortalNav). */
export interface PortalLink {
    key: string;
    label: string;
    url: string;
}

/** A catalog card (WholesaleCatalog::card). */
export interface WholesaleCard {
    id: number;
    name: string;
    image: string | null;
    url: string;
    price_label: string;
    price_from: boolean;
    retail_label: string;
    available: boolean;
    by_piece: boolean;
}

export interface WholesalePage {
    data: WholesaleCard[];
    current_page: number;
    last_page: number;
    total: number;
    prev_url: string | null;
    next_url: string | null;
}

/** One size of one color on the product page. */
export interface GridCell {
    id: number;
    sku: string | null;
    size: string | null;
    orderable: boolean;
    /** Pieces available now; null: no limit. */
    available: number | null;
    /** In stock now (null: no limit); ordered beyond it is a pre-order of the incoming goods. */
    in_stock: number | null;
    incoming: { quantity: number; date: string; label: string } | null;
    unit_price: number | null;
    unit_label: string | null;
    retail_label: string;
    in_cart: number;
}

export interface GridRow {
    key: number;
    name: string | null;
    swatch: string | null;
    cells: (GridCell | null)[];
}

export interface WholesaleProduct {
    id: number;
    name: string;
    slug: string;
    sku: string | null;
    description: string | null;
    image: string | null;
    by_piece: boolean;
    price_label: string;
    price_from: boolean;
    retail_label: string;
    tier: string | null;
    sizes: (string | null)[];
    rows: GridRow[];
    breaks: { min_quantity: number; discount_percent: number; label: string }[];
}

export interface CartLine {
    id: number;
    product_id: number;
    options: { name: string; value: string }[];
    sku: string | null;
    quantity: number;
    available: number | null;
    /** Pieces beyond the stock now: a pre-order of the incoming goods. */
    preorder: number;
    incoming_label: string | null;
    unit_price: number | null;
    unit_label: string | null;
    total: number;
    total_label: string;
    problem: string | null;
}

export interface CartGroup {
    product_id: number;
    name: string;
    url: string | null;
    image: string | null;
    by_piece: boolean;
    quantity: number;
    total: number;
    total_label: string;
    break_label: string | null;
    problem: string | null;
    lines: CartLine[];
}

export interface CartSummary {
    subtotal: number;
    subtotal_label: string;
    pieces: number;
    minimum_value_label: string | null;
    minimum_pieces: number | null;
    minimum_missing: string[];
    /** Set when the order is more than one order can hold. */
    too_large: string | null;
    blocked: boolean;
    can_checkout: boolean;
}

/** A shared PDF catalog link (CatalogPdfController::show). */
export interface CatalogShareLink {
    id: number;
    url: string;
    category: string | null;
    /** retail (the default) or wholesale: the merchant's purchase prices. */
    prices: 'retail' | 'wholesale';
    expires_at: string;
    whatsapp_url: string;
}

/** A statement of account (MerchantStatement::for). */
export interface StatementData {
    shop: string;
    from: string | null;
    to: string | null;
    opening: number;
    opening_label: string;
    rows: {
        id: number;
        date: string;
        type: string;
        type_label: string;
        description: string;
        reference: string | null;
        note?: string;
        amount: number;
        debit_label: string | null;
        credit_label: string | null;
        balance: number;
        balance_label: string;
    }[];
    debits_label: string;
    credits_label: string;
    closing: number;
    closing_label: string;
    credit: { limit_label: string; balance_label: string; open_label: string; room_label: string } | null;
}
