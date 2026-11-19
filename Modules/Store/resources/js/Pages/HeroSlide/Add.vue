<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import HeroSlideFields, { type HeroSlideForm } from './HeroSlideFields.vue';

defineOptions({ layout: AdminLayout });

const form = useForm<HeroSlideForm>({
    eyebrow: '',
    title: '',
    subtitle: '',
    button_label: '',
    link: '',
    sort_order: 0,
    is_active: true,
    starts_at: null,
    ends_at: null,
    image: null,
    mobile_image: null,
    mobile_removed: [],
});

function submit(): void {
    if (form.processing) return;

    form.transform(({ mobile_removed: _removed, ...data }) => data).post(route('hero-slides.store'), { forceFormData: true });
}
</script>

<template>
    <PageHeader :title="t('Add slide')" :parents="[{ title: t('Home slider'), href: route('hero-slides.index') }]" />
    <form @submit.prevent="submit">
        <HeroSlideFields :form="form" />
        <FormActions :processing="form.processing" :cancel-href="route('hero-slides.index')" />
    </form>
</template>
