<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { ExistingImage } from '@/Components/form/ImageUpload.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import HeroSlideFields, { type HeroSlideForm } from './HeroSlideFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    slide: Omit<HeroSlideForm, 'image' | 'mobile_image' | 'mobile_removed'> & { id: number; images: ExistingImage[]; mobile_images: ExistingImage[] };
}>();

const { id: slideId, images, mobile_images: mobileImages, ...values } = props.slide;
const form = useForm<HeroSlideForm>({ ...values, image: null, mobile_image: null, mobile_removed: [] });

function submit(): void {
    if (form.processing) return;

    form.transform(({ mobile_removed: removed, ...data }) => ({ ...data, remove_mobile_image: removed.length > 0, _method: 'put' })).post(
        route('hero-slides.update', slideId),
        { forceFormData: true },
    );
}
</script>

<template>
    <PageHeader :title="t('Edit slide')" :parents="[{ title: t('Home slider'), href: route('hero-slides.index') }]" />
    <form @submit.prevent="submit">
        <HeroSlideFields :form="form" :existing="images" :existing-mobile="mobileImages" />
        <FormActions :processing="form.processing" :cancel-href="route('hero-slides.index')" />
    </form>
</template>
