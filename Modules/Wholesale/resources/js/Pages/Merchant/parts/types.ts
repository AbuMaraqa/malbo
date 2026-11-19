export interface MerchantForm {
    shop_name: string;
    owner_name: string;
    city_id: number | null;
    phone: string;
    commercial_register: string;
    shop_address: string | null;
    tier_id: number | null;
    sales_rep_id: number | null;
    minimum_order_value: number | string | null;
    minimum_order_pieces: number | string | null;
    credit_enabled: boolean;
    credit_limit: number | string | null;
}

/** A merchant staff add (MerchantController::store): its profile and the account it signs in with. */
export interface NewMerchantForm extends MerchantForm {
    email: string;
    password: string;
}

export interface MerchantStatus {
    value: string;
    label: string;
    tone: 'neutral' | 'success' | 'warning' | 'danger';
    next: { value: string; label: string; needs_note: boolean }[];
    approved_at: string | null;
}

export interface HistoryRow {
    id: number;
    from: string | null;
    to: string;
    note: string | null;
    by: string | null;
    at: string | null;
}

/** The ledger page's totals (MerchantLedgerController::index). */
export interface LedgerTotals {
    balance: number;
    balance_label: string;
    invoiced: number;
    invoiced_label: string;
    paid: number;
    paid_label: string;
    credited: number;
    credited_label: string;
    adjusted: number;
    adjusted_label: string;
    /** Invoices whose order is not marked paid. */
    unpaid_invoices: number;
}

export interface LedgerCredit {
    enabled: boolean;
    limit_label: string;
    open_label: string;
    room_label: string;
}

export interface LedgerRow {
    id: number;
    entry_date: string;
    type: string;
    type_label: string;
    type_tone: 'neutral' | 'success' | 'warning' | 'info';
    amount: number;
    amount_label: string;
    running_balance: number;
    running_label: string;
    method: string | null;
    reference: string | null;
    note: string | null;
    order: { number: string; url: string } | null;
    by: string | null;
    receipt_url: string | null;
    /** An invoice whose order is not marked paid. */
    unpaid: boolean;
}
