<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import MultiSelect from '@/Components/form/MultiSelect.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

export interface GovernorateForm {
    name: string;
    code: string;
    country_id: number | null;
}

defineProps<{ form: InertiaForm<GovernorateForm>; countries: Option[] }>();
</script>

<template>
    <div class="grid max-w-3xl gap-4 md:grid-cols-2">
        <TextInput v-model="form.name" class="md:col-span-2" :label="t('Name')" required :error="form.errors.name" />
        <TextInput v-model="form.code" :label="t('Code')" required :maxlength="10" dir="ltr" :error="form.errors.code" />
        <MultiSelect v-model="form.country_id" single :label="t('Country')" required :options="countries" :error="form.errors.country_id" />
    </div>
</template>
