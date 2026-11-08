<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import MultiSelect from '@/Components/form/MultiSelect.vue';
import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

export interface DistrictForm {
    name: string;
    /** Country and governorate only narrow the selects; the server stores the city. */
    country_id: number | null;
    governorate_id: number | null;
    city_id: number | null;
    type: number;
    latitude: number | string | null;
    longitude: number | string | null;
}

export interface GovernorateOption extends Option {
    country_id: number;
}

export interface CityOption extends Option {
    governorate_id: number;
}

const props = defineProps<{
    form: InertiaForm<DistrictForm>;
    countries: Option[];
    governorates: GovernorateOption[];
    cities: CityOption[];
    types: Option[];
}>();

const visibleGovernorates = computed(() => props.governorates.filter((governorate) => governorate.country_id === props.form.country_id));
const visibleCities = computed(() => props.cities.filter((city) => city.governorate_id === props.form.governorate_id));

watch(
    () => props.form.country_id,
    () => {
        if (!visibleGovernorates.value.some((governorate) => governorate.value === props.form.governorate_id)) {
            props.form.governorate_id = null;
        }
    },
);

watch(
    () => props.form.governorate_id,
    () => {
        if (!visibleCities.value.some((city) => city.value === props.form.city_id)) {
            props.form.city_id = null;
        }
    },
);
</script>

<template>
    <div class="grid max-w-3xl gap-4 md:grid-cols-3">
        <TextInput v-model="form.name" class="md:col-span-3" :label="t('Name')" required :error="form.errors.name" />
        <MultiSelect v-model="form.country_id" single :label="t('Country')" required :options="countries" />
        <MultiSelect
            v-model="form.governorate_id"
            single
            :label="t('Governorate')"
            required
            :options="visibleGovernorates"
            :hint="form.country_id ? undefined : t('Choose a country first')"
        />
        <MultiSelect
            v-model="form.city_id"
            single
            :label="t('City')"
            required
            :options="visibleCities"
            :hint="form.governorate_id ? undefined : t('Choose a governorate first')"
            :error="form.errors.city_id"
        />
        <SelectInput v-model="form.type" :label="t('Type')" required :options="types" :error="form.errors.type" />
        <TextInput v-model="form.latitude" :label="t('Latitude')" type="number" step="any" min="-90" max="90" dir="ltr" :error="form.errors.latitude" />
        <TextInput v-model="form.longitude" :label="t('Longitude')" type="number" step="any" min="-180" max="180" dir="ltr" :error="form.errors.longitude" />
    </div>
</template>
