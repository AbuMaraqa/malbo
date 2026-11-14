<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import TierFields, { type TierForm } from './TierFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{ tier: TierForm & { id: number } }>();

const { id: tierId, ...values } = props.tier;
const form = useForm<TierForm>(values);
</script>

<template>
    <PageHeader :title="t('Edit price tier')" :parents="[{ title: t('Price tiers'), href: route('wholesale-tiers.index') }]" />
    <form @submit.prevent="form.put(route('wholesale-tiers.update', tierId))">
        <TierFields :form="form" />
        <FormActions :processing="form.processing" :cancel-href="route('wholesale-tiers.index')" />
    </form>
</template>
