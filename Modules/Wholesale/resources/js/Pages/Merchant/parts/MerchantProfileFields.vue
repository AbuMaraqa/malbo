<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

import type { MerchantForm } from './types';

/** The shop's details, and the terms staff set for it: tier, sales rep and minimum order. */
defineProps<{
    form: InertiaForm<MerchantForm>;
    tiers: Option[];
    salesReps: Option[];
    cities: Option[];
    storeMinimum: { value: string | null; pieces: number | null };
    disabled: boolean;
}>();
</script>

<template>
    <Card :title="t('Shop')">
        <div class="grid gap-4 md:grid-cols-2">
            <TextInput v-model="form.shop_name" :label="t('Shop name')" required :disabled="disabled" :error="form.errors.shop_name" />
            <TextInput v-model="form.owner_name" :label="t('Owner')" required :disabled="disabled" :error="form.errors.owner_name" />
            <TextInput v-model="form.phone" :label="t('Phone')" dir="ltr" required :disabled="disabled" :error="form.errors.phone" />
            <SelectInput v-model="form.city_id" :label="t('City')" :options="cities" :placeholder="t('None')" :disabled="disabled" :error="form.errors.city_id" />
            <TextInput v-model="form.commercial_register" :label="t('Commercial register number')" dir="ltr" required :disabled="disabled" :error="form.errors.commercial_register" />
            <TextareaInput v-model="form.shop_address" class="md:col-span-2" :label="t('Shop address')" :rows="2" :disabled="disabled" :error="form.errors.shop_address" />
        </div>
    </Card>

    <Card :title="t('Wholesale terms')">
        <div class="grid gap-4 md:grid-cols-2">
            <SelectInput
                v-model="form.tier_id"
                :label="t('Price tier')"
                :options="tiers"
                :placeholder="t('Default tier')"
                :hint="t('Empty: the default tier applies')"
                :disabled="disabled"
                :error="form.errors.tier_id"
            />
            <SelectInput v-model="form.sales_rep_id" :label="t('Sales rep')" :options="salesReps" :placeholder="t('None')" :disabled="disabled" :error="form.errors.sales_rep_id" />
            <TextInput
                v-model="form.minimum_order_value"
                type="number"
                min="0"
                step="0.01"
                :label="t('Minimum order value')"
                :hint="storeMinimum.value ? t('Empty: the store minimum (:value)', { value: storeMinimum.value }) : t('Empty: the store minimum')"
                :disabled="disabled"
                :error="form.errors.minimum_order_value"
            />
            <TextInput
                v-model="form.minimum_order_pieces"
                type="number"
                min="0"
                :label="t('Minimum order pieces')"
                :hint="storeMinimum.pieces ? t('Empty: the store minimum (:value)', { value: storeMinimum.pieces }) : t('Empty: the store minimum')"
                :disabled="disabled"
                :error="form.errors.minimum_order_pieces"
            />
        </div>
    </Card>
</template>
