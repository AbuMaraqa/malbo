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
}

/** What is posted: only what the server reads, never a price. */
export function toRequestBody(data: OrderForm, extra: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        ...data,
        ...extra,
        items: data.items.map(({ id, product_id, quantity }) => ({ ...(id ? { id } : {}), product_id, quantity })),
    };
}
