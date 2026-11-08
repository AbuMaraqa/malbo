<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import BranchFields, { type BranchForm, type CityOption } from './BranchFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    branch: BranchForm & { id: number };
    statuses: Option[];
    countries: Option[];
    cities: CityOption[];
    days: Option[];
}>();

const { id: _id, ...values } = props.branch;
const form = useForm<BranchForm>(values);
</script>

<template>
    <PageHeader :title="t('Edit Branch')" :parents="[{ title: t('Branches List'), href: route('branches.index') }]" />
    <form @submit.prevent="form.put(route('branches.update', branch.id))">
        <BranchFields :form="form" :statuses="statuses" :countries="countries" :cities="cities" :days="days" />
        <FormActions :processing="form.processing" :cancel-href="route('branches.index')" />
    </form>
</template>
