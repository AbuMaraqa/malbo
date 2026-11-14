import type { Option } from '@/Components/form/SelectInput.vue';

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand';

/** One status the shipment reached (Modules\Items\Support\ShipmentPresenter::card). */
export interface ShipmentEventRow {
    id: number;
    status: string;
    tone: Tone;
    note: string | null;
    at: string | null;
    by: string | null;
}

export interface CurrentShipment {
    id: number;
    courier: string;
    tracking_number: string | null;
    status: { value: string; label: string; tone: Tone };
    cost: string;
    notes: string | null;
    shipped_at: string | null;
    delivered_at: string | null;
    created_at: string | null;
    created_by: string | null;
    events: ShipmentEventRow[];
    /** Where the shipment may go next; empty once it is final. */
    transitions: Option[];
}

/** The order page's shipment card; null for those who may not see shipments. */
export interface ShipmentCardData {
    current: CurrentShipment | null;
    can_create: boolean;
    couriers: Option[];
}
