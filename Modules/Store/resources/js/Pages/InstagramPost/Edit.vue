<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { ExistingImage } from '@/Components/form/ImageUpload.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import InstagramPostFields, { type InstagramPostForm } from './InstagramPostFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    post: Omit<InstagramPostForm, 'image'> & { id: number; images: ExistingImage[] };
    selectedProducts: Option[];
}>();

const { id: postId, images, ...values } = props.post;
const form = useForm<InstagramPostForm>({ ...values, image: null });

function submit(): void {
    if (form.processing) return;

    form.transform((data) => ({ ...data, _method: 'put' })).post(route('instagram-posts.update', postId), { forceFormData: true });
}
</script>

<template>
    <PageHeader :title="t('Edit Instagram post')" :parents="[{ title: t('Instagram'), href: route('instagram-posts.index') }]" />
    <form @submit.prevent="submit">
        <InstagramPostFields :form="form" :existing="images" :selected-products="selectedProducts" />
        <FormActions :processing="form.processing" :cancel-href="route('instagram-posts.index')" />
    </form>
</template>
