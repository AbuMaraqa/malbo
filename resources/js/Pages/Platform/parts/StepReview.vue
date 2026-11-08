<script setup lang="ts">
import { computed } from 'vue';

import { t } from '@/lib/i18n';

import type { SignUpForm } from './signUpTypes';

const props = defineProps<{ form: SignUpForm; address: string; businessLabel: string; fontLabel: string }>();

const rows = computed(() => [
    { label: t('Store name'), value: props.form.storeName },
    { label: t('Store address'), value: props.address, ltr: true },
    { label: t('What do you sell?'), value: props.businessLabel },
    { label: t('Full name'), value: props.form.ownerName },
    { label: t('Email'), value: props.form.ownerEmail, ltr: true },
    { label: t('Mobile number'), value: props.form.ownerPhone || '—', ltr: true },
    { label: t('Main color'), value: props.form.color, ltr: true },
    { label: t('Font'), value: props.fontLabel },
    { label: t('Logo'), value: props.form.logo?.name ?? '—', ltr: true },
]);
</script>

<template>
    <div class="space-y-4">
        <dl class="divide-y divide-gray-100 rounded-md border border-gray-200 text-sm">
            <div v-for="row in rows" :key="row.label" class="flex justify-between gap-4 px-3 py-2">
                <dt class="text-gray-500">{{ row.label }}</dt>
                <dd class="truncate font-medium" :dir="row.ltr ? 'ltr' : undefined">{{ row.value }}</dd>
            </div>
        </dl>

        <div>
            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input v-model="form.terms" type="checkbox" class="mt-0.5 accent-store-600" />
                {{ t("I agree to use the platform lawfully and to be responsible for my store's products and policies.") }}
            </label>
            <p v-if="form.errors.terms" class="mt-1 text-sm text-danger" role="alert">{{ form.errors.terms }}</p>
        </div>
    </div>
</template>
