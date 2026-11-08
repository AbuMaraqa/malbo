<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import OfferFields, { type OfferForm, type ProductChoice } from './OfferFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{ types: Option[]; branches: Option[]; categories: Option[]; products: ProductChoice[]; submitToken: string }>();

const form = useForm<OfferForm & { submit_token: string }>({
    submit_token: props.submitToken,
    name: '',
    description: '',
    branch_id: [],
    offerable_model: null,
    offerable_id: null,
    type: Number(props.types[0]?.value),
    value: '',
    code: '',
    min_purchase_amount: '',
    usage_limit: '',
    usage_limit_per_user: '',
    start_date: '',
    end_date: '',
    is_active: true,
    image: null,
    removed_image: [],
});

function submit(): void {
    if (form.processing) return;

    form.post(route('offers.store'), { forceFormData: true });
}
</script>

<template>
    <PageHeader :title="t('Add Offer')" :parents="[{ title: t('Offers List'), href: route('offers.index') }]" />
    <form @submit.prevent="submit">
        <OfferFields :form="form" :types="types" :branches="branches" :categories="categories" :products="products" many-branches />
        <FormActions :processing="form.processing" :cancel-href="route('offers.index')" :submit-label="t('Add Offer')" />
    </form>
</template>
