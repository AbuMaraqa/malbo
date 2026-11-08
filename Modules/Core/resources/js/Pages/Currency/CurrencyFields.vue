<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import { t } from '@/lib/i18n';

export interface CurrencyForm {
    name: string;
    plural_name: string;
    code: string;
    symbol: string;
    symbol_position: string;
    decimal_places: number;
    exchange_rate: number | string;
    is_active: boolean;
}

defineProps<{ form: InertiaForm<CurrencyForm>; symbolPositions: Option[] }>();
</script>

<template>
    <div class="grid max-w-3xl gap-4 md:grid-cols-3">
        <TextInput v-model="form.name" class="md:col-span-2" :label="t('Name')" required :error="form.errors.name" />
        <TextInput v-model="form.plural_name" :label="t('Plural name')" :error="form.errors.plural_name" />
        <TextInput v-model="form.code" :label="t('Code')" required :maxlength="3" dir="ltr" :error="form.errors.code" :hint="t('Three letters, like ILS')" />
        <TextInput v-model="form.symbol" :label="t('Symbol')" required :error="form.errors.symbol" />
        <SelectInput v-model="form.symbol_position" :label="t('Position')" required :options="symbolPositions" :error="form.errors.symbol_position" />
        <TextInput v-model="form.decimal_places" :label="t('Decimal places')" type="number" min="0" max="6" required :error="form.errors.decimal_places" />
        <TextInput v-model="form.exchange_rate" :label="t('Exchange rate')" type="number" step="0.0001" required :error="form.errors.exchange_rate" />
        <Toggle v-model="form.is_active" class="md:col-span-3" :label="t('Active')" />
    </div>
</template>
