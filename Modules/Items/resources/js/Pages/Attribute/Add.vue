<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import AttributeFields, { type AttributeForm } from './AttributeFields.vue';

defineOptions({ layout: AdminLayout });
const props = defineProps<{ types: Option[]; colorType: number }>();

const form = useForm<AttributeForm>({
    name: '',
    slug: '',
    type: Number(props.types.find((type) => type.value === 2)?.value ?? props.types[0]?.value),
    description: '',
    values: [],
});
</script>

<template>
    <PageHeader :title="t('Add Attribute')" :parents="[{ title: t('Attributes List'), href: route('attributes.index') }]" />
    <form @submit.prevent="form.post(route('attributes.store'))">
        <AttributeFields :form="form" :types="types" :color-type="colorType" />
        <FormActions :processing="form.processing" :cancel-href="route('attributes.index')" :submit-label="t('Add Attribute')" />
    </form>
</template>
