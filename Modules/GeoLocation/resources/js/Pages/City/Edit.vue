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

const props = defineProps<{
    city: CityForm & { id: number };
    countries: Option[];
    governorates: GovernorateOption[];
    types: Option[];
    capitalTypes: Option[];
}>();

const { id: _id, ...values } = props.city;
const form = useForm<CityForm>(values);
</script>

<template>
    <PageHeader :title="t('Edit City')" :parents="[{ title: t('Cities List'), href: route('cities.index') }]" />
    <form @submit.prevent="form.put(route('cities.update', city.id))">
        <CityFields :form="form" :countries="countries" :governorates="governorates" :types="types" :capital-types="capitalTypes" />
        <FormActions :processing="form.processing" :cancel-href="route('cities.index')" />
    </form>
</template>
