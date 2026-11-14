<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import ShippingZoneFields, { type ShippingZoneForm } from './ShippingZoneFields.vue';

defineOptions({ layout: AdminLayout });

defineProps<{ cities: Option[] }>();

const form = useForm<ShippingZoneForm>({
    name: '',
    fee: 0,
    eta_min_days: 1,
    eta_max_days: 3,
    free_shipping_threshold: null,
    is_active: true,
    sort_order: 0,
    cities: [],
});
</script>

<template>
    <PageHeader :title="t('Add shipping zone')" :parents="[{ title: t('Shipping zones'), href: route('shipping-zones.index') }]" />
    <form @submit.prevent="form.post(route('shipping-zones.store'))">
        <ShippingZoneFields :form="form" :cities="cities" />
        <FormActions :processing="form.processing" :cancel-href="route('shipping-zones.index')" :submit-label="t('Add shipping zone')" />
    </form>
</template>
