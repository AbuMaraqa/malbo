<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import OrderFields from './OrderFields.vue';
import { toRequestBody, type OrderForm, type OrderFormOptions, type ProductOption } from './parts/types';

defineOptions({ layout: AdminLayout });

const props = defineProps<
    OrderFormOptions & {
        order: OrderForm & { id: number; order_number: string; sub_total: string; discount: string; total: string };
        selectedCustomer: Option | null;
        selectedProducts: ProductOption[];
    }
>();

const { id: orderId, order_number: orderNumber, sub_total: _subTotal, discount, total: _total, ...values } = props.order;

const form = useForm<OrderForm>(values);

function submit(): void {
    if (form.processing) return;

    form.transform((data) => toRequestBody(data as OrderForm) as never).put(route('orders.update', orderId));
}
</script>

<template>
    <PageHeader :title="`${t('Edit Order')} ${orderNumber}`" :parents="[{ title: t('Orders List'), href: route('orders.index') }]" />
    <form @submit.prevent="submit">
        <OrderFields :form="form" :options="props" lookup-route="orders.edit-lookup" :selected-customer="selectedCustomer" :selected-products="selectedProducts" :discount="discount" />
        <FormActions :processing="form.processing" :cancel-href="route('orders.index')" />
    </form>
</template>
