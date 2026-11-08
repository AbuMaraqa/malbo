<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import MultiSelect from '@/Components/form/MultiSelect.vue';
import SelectInput from '@/Components/form/SelectInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

import type { OrderForm, OrderFormOptions } from './types';

const props = defineProps<{ form: InertiaForm<OrderForm>; options: OrderFormOptions }>();

const districts = computed(() => props.options.districts.filter((district) => String(district.city_id) === String(props.form.city_id)));

function chooseCity(value: unknown): void {
    if (String(props.form.city_id) !== String(value)) props.form.district_id = null;
    props.form.city_id = value as OrderForm['city_id'];
}
</script>

<template>
    <Card :title="t('Order Information')">
        <div class="grid gap-4">
            <SelectInput v-model="form.status" :label="t('Order Status')" required :options="options.statuses" :error="form.errors.status" />
            <SelectInput v-model="form.payment_method" :label="t('Payment Method')" required :options="options.paymentMethods" :error="form.errors.payment_method" />
            <SelectInput v-model="form.payment_status" :label="t('Payment Status')" required :options="options.paymentStatuses" :error="form.errors.payment_status" />
            <SelectInput v-model="form.delivery_type" :label="t('Delivery Type')" :options="options.deliveryTypes" :error="form.errors.delivery_type" />
            <MultiSelect :model-value="form.city_id" single :label="t('City')" required :options="options.cities" :error="form.errors.city_id" @update:model-value="chooseCity" />
            <MultiSelect v-model="form.district_id" single :label="t('District')" required :options="districts" :error="form.errors.district_id" />
            <TextareaInput v-model="form.delivery_address" :label="t('Delivery Address')" required :error="form.errors.delivery_address" />
            <TextInput v-model="form.contact_phone" :label="t('Contact Phone')" dir="ltr" required :error="form.errors.contact_phone" />
            <TextareaInput v-model="form.notes" :label="t('Notes')" :error="form.errors.notes" />
        </div>
    </Card>
</template>
