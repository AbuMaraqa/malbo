import type { Option } from '@/Components/form/SelectInput.vue';

export interface ProductOption extends Option {
    /** The current price, for the preview. The server works out the real one. */
    price: number;
}

export interface OrderItemRow {
    id?: number;
    product_id: number | string | null;
    quantity: number | string;
    /** Shown only: the server ignores it. */
    price: number | string;
    addons_total: number | string;
    /** Edit only: the product as sold ("Shirt — M") and the line's saved total. */
    name?: string;
    total?: number | string;
    /** A piece of a set or bundle: the set it was sold in. Kept or removed with its set. */
    bundle?: { key: string; name: string | null; quantity: number; unit_price: number } | null;
}

export interface OrderForm {
    user_id: number | string | null;
    branch_id: number | string | null;
    city_id: number | string | null;
    district_id: number | string | null;
    items: OrderItemRow[];
    delivery_fee: number | string;
    status: number;
    payment_method: number;
    payment_status: number;
    delivery_type: number;
    delivery_address: string;
    contact_phone: string;
    notes: string;
    customer_name: string | null;
    customer_email: string | null;
    internal_notes: string | null;
    latitude: number | string | null;
    longitude: number | string | null;
}

export interface DistrictOption extends Option {
    city_id: number;
}

/** The lists the form chooses from; the same props on Add and Edit. */
export interface OrderFormOptions {
    statuses: Option[];
    paymentMethods: Option[];
    paymentStatuses: Option[];
    deliveryTypes: Option[];
    branches: Option[];
    cities: Option[];
    districts: DistrictOption[];
    /** What the server requires: a storefront order has no customer account or branch, and may have no district. */
    required: { user_id: boolean; branch_id: boolean; district_id: boolean };
}

/** What is posted: only what the server reads, never a price. */
export function toRequestBody(data: OrderForm, extra: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        ...data,
        ...extra,
        items: data.items.map(({ id, product_id, quantity }) => ({ ...(id ? { id } : {}), product_id, quantity })),
    };
}
