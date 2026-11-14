<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';

import MerchantAccountCard from './parts/MerchantAccountCard.vue';
import MerchantCreditFields from './parts/MerchantCreditFields.vue';
import MerchantFinanceCard from './parts/MerchantFinanceCard.vue';
import MerchantHistory from './parts/MerchantHistory.vue';
import MerchantProfileFields from './parts/MerchantProfileFields.vue';
import MerchantStatusCard from './parts/MerchantStatusCard.vue';
import type { HistoryRow, MerchantForm, MerchantStatus } from './parts/types';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    merchant: MerchantForm & { id: number; email: string };
    status: MerchantStatus;
    document: { name: string; url: string } | null;
    history: HistoryRow[];
    account: { name: string | null; email: string | null; created_at: string | null };
    storeMinimum: { value: string | null; pieces: number | null };
    finance: { balance_label: string; ledger_url: string };
    tiers: Option[];
    salesReps: Option[];
    cities: Option[];
}>();

const { id: merchantId, ...values } = props.merchant;
const form = useForm<MerchantForm>(values);

function submit(): void {
    form.put(route('merchants.update', merchantId), { preserveScroll: true });
}
</script>

<template>
    <PageHeader :title="merchant.shop_name" :parents="[{ title: t('Merchants'), href: route('merchants.index') }]" />

    <div class="grid items-start gap-4 lg:grid-cols-3">
        <form class="grid gap-4 lg:col-span-2" @submit.prevent="submit">
            <MerchantProfileFields :form="form" :tiers="tiers" :sales-reps="salesReps" :cities="cities" :store-minimum="storeMinimum" :disabled="!can('Merchant Update')" />
            <MerchantCreditFields :form="form" :disabled="!can('Merchant Credit Update')" />
            <FormActions v-if="can('Merchant Update')" :processing="form.processing" :cancel-href="route('merchants.index')" />
        </form>

        <div class="grid gap-4">
            <MerchantStatusCard :merchant-id="merchantId" :status="status" />
            <MerchantFinanceCard :finance="finance" />
            <MerchantAccountCard :account="account" :document="document" :commercial-register="merchant.commercial_register" />
            <MerchantHistory :history="history" />
        </div>
    </div>
</template>
