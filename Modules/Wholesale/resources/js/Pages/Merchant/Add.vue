<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';

import MerchantCreditFields from './parts/MerchantCreditFields.vue';
import MerchantProfileFields from './parts/MerchantProfileFields.vue';
import MerchantSignInFields from './parts/MerchantSignInFields.vue';
import type { NewMerchantForm } from './parts/types';

/**
 * A merchant staff add from the merchants list: its account, shop and terms. It starts approved;
 * its page opens once it is saved.
 */
defineOptions({ layout: AdminLayout });

defineProps<{
    storeMinimum: { value: string | null; pieces: number | null };
    tiers: Option[];
    salesReps: Option[];
    cities: Option[];
}>();

const form = useForm<NewMerchantForm>({
    email: '',
    password: '',
    shop_name: '',
    owner_name: '',
    city_id: null,
    phone: '',
    commercial_register: '',
    shop_address: null,
    tier_id: null,
    sales_rep_id: null,
    minimum_order_value: null,
    minimum_order_pieces: null,
    credit_enabled: false,
    credit_limit: null,
});
</script>

<template>
    <PageHeader :title="t('Add merchant')" :parents="[{ title: t('Merchants'), href: route('merchants.index') }]" />

    <form class="grid gap-4 lg:w-2/3" @submit.prevent="form.post(route('merchants.store'))">
        <MerchantSignInFields :form="form" />
        <MerchantProfileFields :form="form" :tiers="tiers" :sales-reps="salesReps" :cities="cities" :store-minimum="storeMinimum" :disabled="false" />
        <MerchantCreditFields v-if="can('Merchant Credit Update')" :form="form" :disabled="false" />
        <FormActions :processing="form.processing" :cancel-href="route('merchants.index')" />
    </form>
</template>
