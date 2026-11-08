<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import MapPicker from '@/Components/form/MapPicker.vue';
import MultiSelect from '@/Components/form/MultiSelect.vue';
import RemoteSelect from '@/Components/form/RemoteSelect.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import OrderInfoCard from './parts/OrderInfoCard.vue';
import OrderItemsCard from './parts/OrderItemsCard.vue';
import type { OrderForm, OrderFormOptions, ProductOption } from './parts/types';

const props = defineProps<{
    form: InertiaForm<OrderForm>;
    options: OrderFormOptions;
    /** Each page calls the lookup its own permission opens. */
    lookupRoute: string;
    selectedCustomer?: Option | null;
    selectedProducts?: ProductOption[];
    discount?: number | string;
}>();

const DELIVERY = 1;

/** Nablus, where core's map opens. */
const MAP_CENTER: [number, number] = [32.2211, 35.2544];

const isDelivered = computed(() => Number(props.form.delivery_type) === DELIVERY);
const customers = computed(() => (props.selectedCustomer ? [props.selectedCustomer] : []));
</script>

<template>
    <div class="grid items-start gap-4 lg:grid-cols-3">
        <div class="grid gap-4 lg:col-span-2">
            <Card :title="t('Order Details')">
                <div class="grid gap-4 md:grid-cols-2">
                    <RemoteSelect v-model="form.user_id" :url="route(lookupRoute, { type: 'customers' })" :initial="customers" :label="t('Customer')" required :error="form.errors.user_id" />
                    <MultiSelect v-model="form.branch_id" single :label="t('Branch')" required :options="options.branches" :error="form.errors.branch_id" />
                </div>
            </Card>
            <OrderItemsCard :form="form" :lookup-route="lookupRoute" :selected-products="selectedProducts" :discount="discount" :is-delivered="isDelivered" />
            <Card v-if="isDelivered" :title="t('Location On Map')">
                <MapPicker v-model:latitude="form.latitude" v-model:longitude="form.longitude" :center="MAP_CENTER" :error="form.errors.latitude ?? form.errors.longitude" />
            </Card>
        </div>
        <OrderInfoCard :form="form" :options="options" />
    </div>
</template>
