<script setup lang="ts">
import RemoteSelect from '@/Components/form/RemoteSelect.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

export interface ReturnableLine {
    order_item_id: number;
    name: string;
    sku: string | null;
    quantity: number;
    max: number;
    exchange_options: Option[];
}

export interface LineInput {
    order_item_id: number;
    quantity: number | string;
    exchange_product_id: number | null;
}

/**
 * How many units of each order line go back and, for an exchange, the product given instead. The
 * other sizes and colors of the line's product come first; staff may search any product.
 * `errors` are keyed by the line's place on this list: `line.<index>.<field>`.
 */
defineProps<{ lines: ReturnableLine[]; exchanging: boolean; errors: Record<string, string> }>();

const model = defineModel<LineInput[]>({ required: true });
</script>

<template>
    <Card :title="t('Items to send back')">
        <p v-if="!lines.length" class="text-sm text-muted">{{ t('Nothing is left to send back on this order.') }}</p>
        <div v-for="(line, index) in lines" :key="line.order_item_id" class="grid gap-3 border-b border-line py-4 last:border-b-0 md:grid-cols-3">
            <div>
                <p class="font-medium">{{ line.name }}</p>
                <p class="text-sm text-muted">
                    <span v-if="line.sku" dir="ltr">{{ line.sku }} · </span>{{ t('Sold') }}: {{ line.quantity }} · {{ t('Left') }}: {{ line.max }}
                </p>
            </div>
            <TextInput v-model="model[index].quantity" :label="t('Quantity')" type="number" min="0" :max="line.max" :error="errors[`line.${index}.quantity`]" />
            <RemoteSelect
                v-if="exchanging && Number(model[index].quantity) > 0"
                v-model="model[index].exchange_product_id"
                :url="route('return-requests.products')"
                :initial="line.exchange_options"
                :label="t('Exchange for')"
                required
                :error="errors[`line.${index}.exchange_product_id`]"
            />
        </div>
    </Card>
</template>
