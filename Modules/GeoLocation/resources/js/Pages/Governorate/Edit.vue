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

const props = defineProps<{ governorate: GovernorateForm & { id: number }; countries: Option[] }>();

const { id: _id, ...values } = props.governorate;
const form = useForm<GovernorateForm>(values);
</script>

<template>
    <PageHeader :title="t('Edit Governorate')" :parents="[{ title: t('Governorates List'), href: route('governorates.index') }]" />
    <form @submit.prevent="form.put(route('governorates.update', governorate.id))">
        <GovernorateFields :form="form" :countries="countries" />
        <FormActions :processing="form.processing" :cancel-href="route('governorates.index')" />
    </form>
</template>
