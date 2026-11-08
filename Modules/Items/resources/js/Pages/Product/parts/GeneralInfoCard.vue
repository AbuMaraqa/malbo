<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import MultiSelect from '@/Components/form/MultiSelect.vue';
import RichEditor from '@/Components/form/RichEditor.vue';
import TagsInput from '@/Components/form/TagsInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

import type { ProductForm, ProductFormOptions } from './types';
import { slugify } from './variations';

const props = defineProps<{ form: InertiaForm<ProductForm>; options: ProductFormOptions }>();

// The slug follows the name until someone types their own. Arabic stays empty: the server transliterates it.
const slugIsTyped = ref(!!props.form.slug && props.form.slug !== slugify(props.form.name));

watch(
    () => props.form.name,
    (name) => {
        if (!slugIsTyped.value) props.form.slug = slugify(name);
    },
);

function onSlugInput(value: string | number | null | undefined): void {
    props.form.slug = String(value ?? '');
    slugIsTyped.value = props.form.slug !== '';
}

function firstError(prefix: string): string | undefined {
    const key = Object.keys(props.form.errors).find((item) => item === prefix || item.startsWith(`${prefix}.`));

    return key ? (props.form.errors as Record<string, string>)[key] : undefined;
}
</script>

<template>
    <Card :title="t('General information')">
        <div class="grid gap-4 md:grid-cols-2">
            <TextInput v-model="form.name" :label="t('Name')" required :error="form.errors.name" />
            <TextInput :model-value="form.slug" :label="t('Slug')" dir="ltr" :error="form.errors.slug" :hint="t('Leave empty to make it from the name')" @update:model-value="onSlugInput" />
            <TagsInput v-model="form.tags" :label="t('Tags')" :suggestions="options.tagSuggestions" :error="firstError('tags')" />
            <TagsInput v-model="form.labels" :label="t('Labels')" :suggestions="options.labelSuggestions" :error="firstError('labels')" />
            <MultiSelect v-model="form.category_id" :label="t('Category')" required :options="options.categories" :error="firstError('category_id')" />
            <MultiSelect v-model="form.brand_id" single :label="t('Brand')" :options="options.brands" :error="form.errors.brand_id" />
            <MultiSelect v-model="form.branches" class="md:col-span-2" :label="t('Branches')" :options="options.branches" :error="firstError('branches')" />
            <TextareaInput v-model="form.short_description" class="md:col-span-2" :label="t('Short Description')" :error="form.errors.short_description" />
            <RichEditor v-model="form.description" class="md:col-span-2" :label="t('Description')" :error="form.errors.description" />
        </div>
    </Card>
</template>
