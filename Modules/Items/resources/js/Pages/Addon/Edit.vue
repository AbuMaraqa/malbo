<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import AddonFields, { type AddonForm, type AddonOptionRow } from './AddonFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    addon: Omit<AddonForm, 'options'> & { id: number; options: Omit<AddonOptionRow, 'image' | 'removed_image'>[] };
    types: Option[];
}>();

const { id: _id, options, ...values } = props.addon;
const form = useForm<AddonForm>({ ...values, options: options.map((option) => ({ ...option, image: null, removed_image: [] })) });

function submit(): void {
    form.transform((data) => ({ ...data, _method: 'put' })).post(route('addons.update', props.addon.id), { forceFormData: true });
}
</script>

<template>
    <PageHeader :title="t('Edit Addon')" :parents="[{ title: t('Addons List'), href: route('addons.index') }]" />
    <form @submit.prevent="submit">
        <AddonFields :form="form" :types="types" collapsed />
        <FormActions :processing="form.processing" :cancel-href="route('addons.index')" />
    </form>
</template>
