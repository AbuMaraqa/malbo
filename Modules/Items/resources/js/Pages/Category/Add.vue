<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { TreeNode } from '@/Components/form/TreeSelectNode.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import CategoryFields, { type CategoryForm } from './CategoryFields.vue';

defineOptions({ layout: AdminLayout });
defineProps<{ parents: TreeNode[] }>();

const form = useForm<CategoryForm>({
    name: '',
    slug: '',
    parent_id: null,
    description: '',
    status: true,
    sort_order: 0,
    image: null,
    removed_image: [],
});
</script>

<template>
    <PageHeader :title="t('Add Category')" :parents="[{ title: t('Categories List'), href: route('categories.index') }]" />
    <form @submit.prevent="form.post(route('categories.store'), { forceFormData: true })">
        <CategoryFields :form="form" :parents="parents" />
        <FormActions :processing="form.processing" :cancel-href="route('categories.index')" :submit-label="t('Add Category')" />
    </form>
</template>
