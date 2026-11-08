<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import CityFields, { type CityForm, type GovernorateOption } from './CityFields.vue';

defineOptions({ layout: AdminLayout });
defineProps<{ countries: Option[]; governorates: GovernorateOption[]; types: Option[]; capitalTypes: Option[] }>();

const form = useForm<CityForm>({
    name: '',
    country_id: null,
    governorate_id: null,
    population: null,
    latitude: null,
    longitude: null,
    type: 1,
    is_capital: false,
    capital_type: null,
});
</script>

<template>
    <PageHeader :title="t('Add City')" :parents="[{ title: t('Cities List'), href: route('cities.index') }]" />
    <form @submit.prevent="form.post(route('cities.store'))">
        <CityFields :form="form" :countries="countries" :governorates="governorates" :types="types" :capital-types="capitalTypes" />
        <FormActions :processing="form.processing" :cancel-href="route('cities.index')" :submit-label="t('Add City')" />
    </form>
</template>
