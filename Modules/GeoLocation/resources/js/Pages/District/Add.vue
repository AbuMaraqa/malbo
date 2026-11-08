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
defineProps<{ countries: Option[]; governorates: GovernorateOption[]; cities: CityOption[]; types: Option[] }>();

const form = useForm<DistrictForm>({
    name: '',
    country_id: null,
    governorate_id: null,
    city_id: null,
    type: 1,
    latitude: null,
    longitude: null,
});
</script>

<template>
    <PageHeader :title="t('Add District')" :parents="[{ title: t('Districts List'), href: route('districts.index') }]" />
    <form @submit.prevent="form.post(route('districts.store'))">
        <DistrictFields :form="form" :countries="countries" :governorates="governorates" :cities="cities" :types="types" />
        <FormActions :processing="form.processing" :cancel-href="route('districts.index')" :submit-label="t('Add District')" />
    </form>
</template>
