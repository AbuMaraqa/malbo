<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import MultiSelect from '@/Components/form/MultiSelect.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

export interface CountryForm {
    name: string;
    code: string;
    phone_code: string;
    currency_id: number | null;
}

defineProps<{ form: InertiaForm<CountryForm>; currencies: Option[] }>();
</script>

<template>
    <div class="grid max-w-3xl gap-4 md:grid-cols-3">
        <TextInput v-model="form.name" class="md:col-span-3" :label="t('Name')" required :error="form.errors.name" />
        <TextInput v-model="form.code" :label="t('Code')" required :maxlength="2" dir="ltr" :error="form.errors.code" />
        <TextInput v-model="form.phone_code" :label="t('Phone Code')" required :maxlength="10" dir="ltr" :error="form.errors.phone_code" />
        <MultiSelect v-model="form.currency_id" single :label="t('Currency')" required :options="currencies" :error="form.errors.currency_id" />
    </div>
</template>
