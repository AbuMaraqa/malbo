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

const props = defineProps<{ country: CountryForm & { id: number }; currencies: Option[] }>();

const { id: _id, ...values } = props.country;
const form = useForm<CountryForm>(values);
</script>

<template>
    <PageHeader :title="t('Edit Country')" :parents="[{ title: t('Countries List'), href: route('countries.index') }]" />
    <form @submit.prevent="form.put(route('countries.update', country.id))">
        <CountryFields :form="form" :currencies="currencies" />
        <FormActions :processing="form.processing" :cancel-href="route('countries.index')" />
    </form>
</template>
