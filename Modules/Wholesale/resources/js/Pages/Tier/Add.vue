<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import TierFields, { type TierForm } from './TierFields.vue';

defineOptions({ layout: AdminLayout });

const form = useForm<TierForm>({ name: '', discount_percent: 0, sort_order: 0, is_default: false });
</script>

<template>
    <PageHeader :title="t('Add price tier')" :parents="[{ title: t('Price tiers'), href: route('wholesale-tiers.index') }]" />
    <form @submit.prevent="form.post(route('wholesale-tiers.store'))">
        <TierFields :form="form" />
        <FormActions :processing="form.processing" :cancel-href="route('wholesale-tiers.index')" />
    </form>
</template>
