<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import BundleFields from './BundleFields.vue';
import { emptyBundle, type BundleForm, type BundleFormOptions } from './parts/types';

defineOptions({ layout: AdminLayout });

const props = defineProps<BundleFormOptions>();

const form = useForm<BundleForm>(emptyBundle());

function submit(): void {
    if (form.processing) return;

    form.post(route('bundles.store'), { forceFormData: true });
}
</script>

<template>
    <PageHeader :title="t('Add set or bundle')" :parents="[{ title: t('Sets and bundles'), href: route('bundles.index') }]" />
    <form @submit.prevent="submit">
        <BundleFields :form="form" :options="props" />
        <FormActions :processing="form.processing" :cancel-href="route('bundles.index')" />
    </form>
</template>
