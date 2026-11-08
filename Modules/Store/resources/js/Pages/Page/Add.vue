<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import PageFields, { type PageForm } from './PageFields.vue';

defineOptions({ layout: AdminLayout });

const form = useForm<PageForm>({
    title: '',
    body: '',
    meta_description: '',
    slug: '',
    is_published: true,
    show_in_footer: true,
    sort_order: 100,
});
</script>

<template>
    <PageHeader :title="t('Add page')" :parents="[{ title: t('Store pages'), href: route('pages.index') }]" />
    <form @submit.prevent="form.post(route('pages.store'))">
        <PageFields :form="form" />
        <FormActions :processing="form.processing" :cancel-href="route('pages.index')" :submit-label="t('Add page')" />
    </form>
</template>
