<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import MultiSelect from '@/Components/form/MultiSelect.vue';
import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import { t } from '@/lib/i18n';

export interface CityForm {
    name: string;
    /** Only narrows the governorates; the server stores the governorate. */
    country_id: number | null;
    governorate_id: number | null;
    population: number | string | null;
    latitude: number | string | null;
    longitude: number | string | null;
    type: number;
    is_capital: boolean;
    capital_type: number | null;
}

export interface GovernorateOption extends Option {
    country_id: number;
}

const props = defineProps<{
    form: InertiaForm<CityForm>;
    countries: Option[];
    governorates: GovernorateOption[];
    types: Option[];
    capitalTypes: Option[];
}>();

const visibleGovernorates = computed(() => props.governorates.filter((governorate) => governorate.country_id === props.form.country_id));

watch(
    () => props.form.country_id,
    () => {
        if (!visibleGovernorates.value.some((governorate) => governorate.value === props.form.governorate_id)) {
            props.form.governorate_id = null;
        }
    },
);

watch(
    () => props.form.is_capital,
    (isCapital) => {
        props.form.capital_type = isCapital ? (props.form.capital_type ?? Number(props.capitalTypes[0]?.value)) : null;
    },
);
</script>

<template>
    <div class="grid max-w-3xl gap-4 md:grid-cols-2">
        <TextInput v-model="form.name" class="md:col-span-2" :label="t('Name')" required :error="form.errors.name" />
        <MultiSelect v-model="form.country_id" single :label="t('Country')" required :options="countries" />
        <MultiSelect
            v-model="form.governorate_id"
            single
            :label="t('Governorate')"
            required
            :options="visibleGovernorates"
            :hint="form.country_id ? undefined : t('Choose a country first')"
            :error="form.errors.governorate_id"
        />
        <TextInput v-model="form.population" :label="t('Population')" type="number" min="0" step="1" :error="form.errors.population" />
        <SelectInput v-model="form.type" :label="t('Type')" required :options="types" :error="form.errors.type" />
        <TextInput v-model="form.latitude" :label="t('Latitude')" type="number" step="any" min="-90" max="90" dir="ltr" :error="form.errors.latitude" />
        <TextInput v-model="form.longitude" :label="t('Longitude')" type="number" step="any" min="-180" max="180" dir="ltr" :error="form.errors.longitude" />
        <Toggle v-model="form.is_capital" class="self-center" :label="t('Is Capital')" />
        <SelectInput v-if="form.is_capital" v-model="form.capital_type" :label="t('Capital Type')" required :options="capitalTypes" :error="form.errors.capital_type" />
    </div>
</template>
