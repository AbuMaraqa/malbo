<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import CurrencyFields, { type CurrencyForm } from './CurrencyFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{ currency: CurrencyForm & { id: number }; symbolPositions: Option[] }>();

const { id: _id, ...values } = props.currency;
const form = useForm<CurrencyForm>(values);
</script>

<template>
    <PageHeader :title="t('Edit Currency')" :parents="[{ title: t('Currencies List'), href: route('currencies.index') }]" />
    <form @submit.prevent="form.put(route('currencies.update', currency.id))">
        <CurrencyFields :form="form" :symbol-positions="symbolPositions" />
        <FormActions :processing="form.processing" :cancel-href="route('currencies.index')" />
    </form>
</template>
