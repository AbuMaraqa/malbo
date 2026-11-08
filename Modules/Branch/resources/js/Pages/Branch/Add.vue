<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import BranchFields, { type BranchForm, type CityOption } from './BranchFields.vue';
import type { WorkingHour } from './parts/WorkingHoursFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    statuses: Option[];
    countries: Option[];
    cities: CityOption[];
    days: Option[];
    workingHours: WorkingHour[];
}>();

const form = useForm<BranchForm>({
    name: '',
    description: '',
    address: '',
    email: null,
    phone: null,
    latitude: null,
    longitude: null,
    opening_time: '08:00',
    closing_time: '17:00',
    country_id: null,
    city_id: null,
    status: 1,
    working_hours: props.workingHours,
});
</script>

<template>
    <PageHeader :title="t('Add Branch')" :parents="[{ title: t('Branches List'), href: route('branches.index') }]" />
    <form @submit.prevent="form.post(route('branches.store'))">
        <BranchFields :form="form" :statuses="statuses" :countries="countries" :cities="cities" :days="days" />
        <FormActions :processing="form.processing" :cancel-href="route('branches.index')" :submit-label="t('Add Branch')" />
    </form>
</template>
