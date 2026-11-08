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
defineProps<{ symbolPositions: Option[] }>();

const form = useForm<CurrencyForm>({
    name: '',
    plural_name: '',
    code: '',
    symbol: '',
    symbol_position: 'before',
    decimal_places: 2,
    exchange_rate: 1,
    is_active: true,
});
</script>

<template>
    <PageHeader :title="t('Add Currency')" :parents="[{ title: t('Currencies List'), href: route('currencies.index') }]" />
    <form @submit.prevent="form.post(route('currencies.store'))">
        <CurrencyFields :form="form" :symbol-positions="symbolPositions" />
        <FormActions :processing="form.processing" :cancel-href="route('currencies.index')" :submit-label="t('Add Currency')" />
    </form>
</template>
