<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import GovernorateFields, { type GovernorateForm } from './GovernorateFields.vue';

defineOptions({ layout: AdminLayout });
defineProps<{ countries: Option[] }>();

const form = useForm<GovernorateForm>({
    name: '',
    code: '',
    country_id: null,
});
</script>

<template>
    <PageHeader :title="t('Add Governorate')" :parents="[{ title: t('Governorates List'), href: route('governorates.index') }]" />
    <form @submit.prevent="form.post(route('governorates.store'))">
        <GovernorateFields :form="form" :countries="countries" />
        <FormActions :processing="form.processing" :cancel-href="route('governorates.index')" :submit-label="t('Add Governorate')" />
    </form>
</template>
