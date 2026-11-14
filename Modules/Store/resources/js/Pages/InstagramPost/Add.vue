<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import InstagramPostFields, { type InstagramPostForm } from './InstagramPostFields.vue';

defineOptions({ layout: AdminLayout });

const form = useForm<InstagramPostForm>({ permalink: '', caption: '', posted_at: null, sort_order: 0, is_active: true, products: [], image: null });

function submit(): void {
    if (form.processing) return;

    form.post(route('instagram-posts.store'), { forceFormData: true });
}
</script>

<template>
    <PageHeader :title="t('Add Instagram post')" :parents="[{ title: t('Instagram'), href: route('instagram-posts.index') }]" />
    <form @submit.prevent="submit">
        <InstagramPostFields :form="form" />
        <FormActions :processing="form.processing" :cancel-href="route('instagram-posts.index')" />
    </form>
</template>
