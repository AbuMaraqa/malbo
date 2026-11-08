<script setup lang="ts">
/**
 * The order's lines and a live preview of the totals. The preview is a convenience: on save the
 * server prices every line itself and stores its own totals.
 */
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import RemoteSelect from '@/Components/form/RemoteSelect.vue';
import Repeater from '@/Components/form/Repeater.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import type { OrderForm, OrderItemRow, ProductOption } from './types';

const props = defineProps<{
    form: InertiaForm<OrderForm>;
    /** Which lookup route this page may call. */
    lookupRoute: string;
    selectedProducts?: ProductOption[];
    /** Already on the order, set when it was placed. */
    discount?: number | string;
    isDelivered: boolean;
}>();

const errors = computed(() => props.form.errors as Record<string, string | undefined>);
const newItem = (): OrderItemRow => ({ product_id: null, quantity: 1, price: 0, addons_total: 0 });

const money = (value: number): string => value.toFixed(2);

const subTotal = computed(() => props.form.items.reduce((sum, item) => sum + (Number(item.price) || 0) * (Number(item.quantity) || 0) + (Number(item.addons_total) || 0), 0));
const fee = computed(() => (props.isDelivered ? Number(props.form.delivery_fee) || 0 : 0));
const grandTotal = computed(() => Math.max(0, subTotal.value - (Number(props.discount) || 0)) + fee.value);

function picked(item: OrderItemRow, option: ProductOption): void {
    item.price = option.price;
    item.addons_total = 0;
}
</script>

<template>
    <Card :title="t('Products')">
        <Repeater
            v-model="form.items"
            name="items"
            :errors="errors"
            :new-item="newItem"
            :add-label="t('Add Product')"
            :empty-text="t('No products yet')"
            :reorderable="false"
        >
            <template #item="{ item, error }">
                <div class="grid gap-3 md:grid-cols-3">
                    <RemoteSelect
                        v-model="item.product_id"
                        :url="route(lookupRoute, { type: 'products' })"
                        :initial="selectedProducts"
                        :label="t('Product')"
                        :placeholder="item.id && !item.product_id ? t('Deleted product') : undefined"
                        :required="!item.id"
                        :error="error('product_id')"
                        @picked="picked(item, $event)"
                    />
                    <TextInput v-model="item.quantity" :label="t('Quantity')" type="number" min="1" required :error="error('quantity')" />
                    <TextInput :model-value="item.price" :label="t('Price per unit')" disabled />
                </div>
            </template>
        </Repeater>

        <div class="mt-5 grid gap-4 border-t border-line pt-4 md:grid-cols-3">
            <TextInput :model-value="money(subTotal)" :label="t('Subtotal')" disabled />
            <TextInput v-if="isDelivered" v-model="form.delivery_fee" :label="t('Delivery Fee')" type="number" step="0.01" min="0" :error="form.errors.delivery_fee" />
            <TextInput :model-value="money(grandTotal)" :label="t('Grand Total')" disabled :hint="t('Calculated again by the server when saved')" />
        </div>
        <p v-if="Number(discount) > 0" class="mt-2 text-sm text-muted">{{ t('Discount') }}: {{ discount }}</p>
    </Card>
</template>
