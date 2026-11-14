<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';

import OrderFields from './OrderFields.vue';
import OrderPrintActions from './parts/OrderPrintActions.vue';
import OrderStatusHistory, { type StatusHistoryRow } from './parts/OrderStatusHistory.vue';
import type { ShipmentCardData } from './parts/shipment';
import ShipmentCard from './parts/ShipmentCard.vue';
import { toRequestBody, type OrderForm, type OrderFormOptions, type OrderItemRow, type ProductOption } from './parts/types';

defineOptions({ layout: AdminLayout });

const props = defineProps<
    OrderFormOptions & {
        order: OrderForm & { id: number; order_number: string; sub_total: string; discount: string; total: string; channel: string; coupon_code: string | null };
        selectedCustomer: Option | null;
        selectedProducts: ProductOption[];
        canConfirm: boolean;
        canRequestReturn: boolean;
        /** Delivered (or later) or with a return request: the lines are shown, not edited. */
        linesLocked: boolean;
        /** The coupon no longer covers the lines (offer gone, or below its minimum): saving sets its discount to 0. */
        couponNoLongerApplies: boolean;
        history: StatusHistoryRow[];
        /** Null for those who may not see shipments. */
        shipment: ShipmentCardData | null;
    }
>();

const {
    id: orderId,
    order_number: orderNumber,
    sub_total: _subTotal,
    discount,
    total: _total,
    channel: _channel,
    coupon_code: couponCode,
    ...values
} = props.order;

// The pieces of a set stay as sold: they are shown apart, sent back unchanged, or all removed.
const bundleItems = ref<OrderItemRow[]>(values.items.filter((item) => item.bundle));
const form = useForm<OrderForm>({ ...values, items: values.items.filter((item) => !item.bundle) });

function removeBundle(key: string): void {
    bundleItems.value = bundleItems.value.filter((item) => item.bundle?.key !== key);
}
const confirming = ref(false);

/**
 * The page keeps its state after confirming the order or moving its shipment (both change the
 * order's status on the server): the status follows the saved one, every other unsaved edit stays.
 */
watch(
    () => props.order.status,
    (status) => {
        form.defaults('status', status);
        form.status = status;
    },
);

function submit(): void {
    if (form.processing) return;

    form.transform((data) => toRequestBody({ ...(data as OrderForm), items: [...bundleItems.value, ...(data as OrderForm).items] }) as never).put(route('orders.update', orderId));
}

/** The buyer was called and the order goes ahead: awaiting confirmation → confirmed. */
async function confirmOrder(): Promise<void> {
    const confirmed = await confirm({
        title: t('Confirm order :number?', { number: orderNumber }),
        description: t('The order moves to Confirmed and can be prepared.'),
        confirmLabel: t('Confirm order'),
        tone: 'primary',
    });
    if (!confirmed) return;

    confirming.value = true;
    router.put(route('orders.confirm', orderId), {}, { preserveScroll: true, onFinish: () => (confirming.value = false) });
}
</script>

<template>
    <PageHeader :title="`${t('Edit Order')} ${orderNumber}`" :parents="[{ title: t('Orders List'), href: route('orders.index') }]">
        <template v-if="can('Order Invoice') || (canConfirm && can('Order Update')) || (canRequestReturn && can('Return Request Create'))" #actions>
            <OrderPrintActions v-if="can('Order Invoice')" :order-id="orderId" />
            <Button v-if="canRequestReturn && can('Return Request Create')" variant="secondary" :href="route('return-requests.add', { order: orderNumber })">
                {{ t('Return or exchange') }}
            </Button>
            <Button v-if="canConfirm && can('Order Update')" variant="primary" :loading="confirming" @click="confirmOrder">{{ t('Confirm order') }}</Button>
        </template>
    </PageHeader>
    <p v-if="couponCode" class="mb-4 text-sm text-muted">
        {{ t('Coupon used') }}: <span dir="ltr" class="font-medium text-ink">{{ couponCode }}</span>
    </p>
    <p v-if="couponCode && couponNoLongerApplies" role="alert" class="mb-4 rounded-control border border-warning-500/40 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
        {{ t('The coupon :code no longer applies to these items: saving sets its discount to 0.', { code: couponCode }) }}
    </p>
    <!-- Its own forms: kept outside the order's form, which cannot hold another. -->
    <ShipmentCard v-if="shipment" :order-id="orderId" :shipment="shipment" class="mb-4" />
    <form @submit.prevent="submit">
        <OrderFields
            :form="form"
            :options="props"
            lookup-route="orders.edit-lookup"
            :selected-customer="selectedCustomer"
            :selected-products="selectedProducts"
            :discount="discount"
            :lines-locked="linesLocked"
            :bundle-items="bundleItems"
            @remove-bundle="removeBundle"
        />
        <OrderStatusHistory :history="history" class="mt-4" />
        <FormActions :processing="form.processing" :cancel-href="route('orders.index')" />
    </form>
</template>
