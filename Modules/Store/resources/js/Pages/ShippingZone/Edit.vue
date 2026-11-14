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

const props = defineProps<{ zone: ShippingZoneForm & { id: number }; cities: Option[] }>();

const { id: zoneId, ...values } = props.zone;
const form = useForm<ShippingZoneForm>(values);
</script>

<template>
    <PageHeader :title="t('Edit shipping zone')" :parents="[{ title: t('Shipping zones'), href: route('shipping-zones.index') }]" />
    <form @submit.prevent="form.put(route('shipping-zones.update', zoneId))">
        <ShippingZoneFields :form="form" :cities="cities" />
        <FormActions :processing="form.processing" :cancel-href="route('shipping-zones.index')" />
    </form>
</template>
