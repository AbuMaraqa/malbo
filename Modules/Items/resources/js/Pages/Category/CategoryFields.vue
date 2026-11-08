<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import ImageUpload, { type ExistingImage } from '@/Components/form/ImageUpload.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import TreeSelect from '@/Components/form/TreeSelect.vue';
import type { TreeNode } from '@/Components/form/TreeSelectNode.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

export interface CategoryForm {
    name: string;
    slug: string;
    parent_id: number | null;
    description: string;
    status: boolean;
    sort_order: number | string;
    image: File | File[] | null;
    removed_image: (number | string)[];
}

defineProps<{ form: InertiaForm<CategoryForm>; parents: TreeNode[]; images?: ExistingImage[] }>();
</script>

<template>
    <div class="grid items-start gap-4 lg:grid-cols-3">
        <Card class="lg:col-span-2">
            <div class="grid gap-4 md:grid-cols-2">
                <TextInput v-model="form.name" :label="t('Name')" required :error="form.errors.name" />
                <TextInput v-model="form.slug" :label="t('Slug')" dir="ltr" :error="form.errors.slug" :hint="t('Leave empty to make it from the name')" />
                <TreeSelect v-model="form.parent_id as number | null" class="md:col-span-2" :nodes="parents" :label="t('Parent Category')" :placeholder="t('Select Parent Category')" :error="form.errors.parent_id" />
                <TextareaInput v-model="form.description" class="md:col-span-2" :label="t('Description')" :error="form.errors.description" />
                <Toggle v-model="form.status" :label="t('Active')" />
                <TextInput v-model="form.sort_order" :label="t('Sort Order')" type="number" min="0" :error="form.errors.sort_order" />
            </div>
        </Card>
        <Card>
            <ImageUpload v-model="form.image" v-model:removed="form.removed_image" :existing="images" :label="t('Image')" required :error="form.errors.image" />
        </Card>
    </div>
</template>
