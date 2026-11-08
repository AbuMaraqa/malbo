<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import AttributeFields, { type AttributeForm, type AttributeValueRow } from './AttributeFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{ attribute: AttributeForm & { id: number }; types: Option[]; colorType: number }>();

const { id: _id, ...values } = props.attribute;
const form = useForm<AttributeForm>(values);

/** A drag saves the order of the saved values at once; new rows get their place when the form is saved. */
function saveOrder(rows: AttributeValueRow[]): void {
    const ids = rows.flatMap((row) => (row.id ? [row.id] : []));

    if (ids.length > 1) {
        router.put(route('attributes.values.reorder', props.attribute.id), { ids }, { preserveScroll: true, preserveState: true, only: ['flash'] });
    }
}
</script>

<template>
    <PageHeader :title="t('Edit Attribute')" :parents="[{ title: t('Attributes List'), href: route('attributes.index') }]" />
    <form @submit.prevent="form.put(route('attributes.update', attribute.id))">
        <AttributeFields :form="form" :types="types" :color-type="colorType" @reorder="saveOrder" />
        <FormActions :processing="form.processing" :cancel-href="route('attributes.index')" />
    </form>
</template>
