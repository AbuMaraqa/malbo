<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { ExistingImage } from '@/Components/form/ImageUpload.vue';
import type { TreeNode } from '@/Components/form/TreeSelectNode.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import CategoryFields, { type CategoryForm } from './CategoryFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    category: Omit<CategoryForm, 'image' | 'removed_image'> & { id: number };
    images: ExistingImage[];
    parents: TreeNode[];
}>();

const { id: _id, ...values } = props.category;
const form = useForm<CategoryForm>({ ...values, image: null, removed_image: [] });

function submit(): void {
    form.transform((data) => ({ ...data, _method: 'put' })).post(route('categories.update', props.category.id), { forceFormData: true });
}
</script>

<template>
    <PageHeader :title="t('Edit Category')" :parents="[{ title: t('Categories List'), href: route('categories.index') }]" />
    <form @submit.prevent="submit">
        <CategoryFields :form="form" :parents="parents" :images="images" />
        <FormActions :processing="form.processing" :cancel-href="route('categories.index')" />
    </form>
</template>
