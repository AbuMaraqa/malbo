<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import AddonFields, { type AddonForm } from './AddonFields.vue';

defineOptions({ layout: AdminLayout });
const props = defineProps<{ types: Option[] }>();

const form = useForm<AddonForm>({
    name: '',
    type: Number(props.types[0]?.value),
    description: '',
    options: [{ name: '', price: '', is_default: false, is_quantifiable: false, description: '', image: null, removed_image: [], images: [] }],
});
</script>

<template>
    <PageHeader :title="t('Add Addon')" :parents="[{ title: t('Addons List'), href: route('addons.index') }]" />
    <form @submit.prevent="form.post(route('addons.store'), { forceFormData: true })">
        <AddonFields :form="form" :types="types" />
        <FormActions :processing="form.processing" :cancel-href="route('addons.index')" :submit-label="t('Add Addon')" />
    </form>
</template>
