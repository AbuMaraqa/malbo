<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import CountryFields, { type CountryForm } from './CountryFields.vue';

defineOptions({ layout: AdminLayout });
defineProps<{ currencies: Option[] }>();

const form = useForm<CountryForm>({
    name: '',
    code: '',
    phone_code: '',
    currency_id: null,
});
</script>

<template>
    <PageHeader :title="t('Add Country')" :parents="[{ title: t('Countries List'), href: route('countries.index') }]" />
    <form @submit.prevent="form.post(route('countries.store'))">
        <CountryFields :form="form" :currencies="currencies" />
        <FormActions :processing="form.processing" :cancel-href="route('countries.index')" :submit-label="t('Add Country')" />
    </form>
</template>
