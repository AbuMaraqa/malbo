<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import DistrictFields, { type CityOption, type DistrictForm, type GovernorateOption } from './DistrictFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    district: DistrictForm & { id: number };
    countries: Option[];
    governorates: GovernorateOption[];
    cities: CityOption[];
    types: Option[];
}>();

const { id: _id, ...values } = props.district;
const form = useForm<DistrictForm>(values);
</script>

<template>
    <PageHeader :title="t('Edit District')" :parents="[{ title: t('Districts List'), href: route('districts.index') }]" />
    <form @submit.prevent="form.put(route('districts.update', district.id))">
        <DistrictFields :form="form" :countries="countries" :governorates="governorates" :cities="cities" :types="types" />
        <FormActions :processing="form.processing" :cancel-href="route('districts.index')" />
    </form>
</template>
