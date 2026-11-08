<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import MapPicker from '@/Components/form/MapPicker.vue';
import MultiSelect from '@/Components/form/MultiSelect.vue';
import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

import WorkingHoursFields, { type WorkingHour } from './parts/WorkingHoursFields.vue';

export interface BranchForm {
    name: string;
    description: string;
    address: string;
    email: string | null;
    phone: string | null;
    latitude: number | string | null;
    longitude: number | string | null;
    opening_time: string | null;
    closing_time: string | null;
    country_id: number | null;
    city_id: number | null;
    status: number;
    working_hours: WorkingHour[];
}

export interface CityOption extends Option {
    country_id: number | null;
}

const props = defineProps<{
    form: InertiaForm<BranchForm>;
    statuses: Option[];
    countries: Option[];
    cities: CityOption[];
    days: Option[];
}>();

const visibleCities = computed(() => props.cities.filter((city) => city.country_id === props.form.country_id));

watch(
    () => props.form.country_id,
    () => {
        if (!visibleCities.value.some((city) => city.value === props.form.city_id)) {
            props.form.city_id = null;
        }
    },
);
</script>

<template>
    <div class="grid max-w-4xl gap-6">
        <Card :title="t('General information')">
            <div class="grid gap-4 md:grid-cols-2">
                <TextInput v-model="form.name" class="md:col-span-2" :label="t('Name')" required :error="form.errors.name" />
                <TextInput v-model="form.email" :label="t('Email')" type="email" dir="ltr" :error="form.errors.email" />
                <TextInput v-model="form.phone" :label="t('Phone')" type="tel" dir="ltr" :error="form.errors.phone" />
                <SelectInput v-model="form.status" :label="t('Status')" required :options="statuses" :error="form.errors.status" />
                <TextareaInput v-model="form.description" class="md:col-span-2" :label="t('Description')" :error="form.errors.description" />
            </div>
        </Card>

        <Card :title="t('Location')">
            <div class="grid gap-4 md:grid-cols-2">
                <MultiSelect v-model="form.country_id" single :label="t('Country')" required :options="countries" :error="form.errors.country_id" />
                <MultiSelect
                    v-model="form.city_id"
                    single
                    :label="t('City')"
                    required
                    :options="visibleCities"
                    :hint="form.country_id ? undefined : t('Choose a country first')"
                    :error="form.errors.city_id"
                />
                <TextareaInput v-model="form.address" class="md:col-span-2" :label="t('Address')" :error="form.errors.address" />
                <MapPicker
                    v-model:latitude="form.latitude"
                    v-model:longitude="form.longitude"
                    class="md:col-span-2"
                    :label="t('Location')"
                    :error="form.errors.latitude ?? form.errors.longitude"
                />
            </div>
        </Card>

        <Card :title="t('Weekly Schedule')">
            <div class="mb-4 grid gap-4 md:grid-cols-2">
                <TextInput v-model="form.opening_time" :label="t('Opening Time')" type="time" dir="ltr" :error="form.errors.opening_time" />
                <TextInput v-model="form.closing_time" :label="t('Closing Time')" type="time" dir="ltr" :error="form.errors.closing_time" />
            </div>
            <WorkingHoursFields :hours="form.working_hours" :days="days" :errors="form.errors as Record<string, string | undefined>" />
        </Card>
    </div>
</template>
