<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { t } from '@/lib/i18n';

defineOptions({ layout: GuestLayout });

const recovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });
</script>

<template>
    <Head :title="t('Two-factor code')" />
    <h1 class="mb-4 text-base font-semibold">{{ t('Two-factor code') }}</h1>
    <form class="space-y-4" @submit.prevent="form.post('/two-factor-challenge')">
        <TextInput v-if="!recovery" v-model="form.code" :label="t('Code from your authenticator app')" autocomplete="one-time-code" dir="ltr" :error="form.errors.code" />
        <TextInput v-else v-model="form.recovery_code" :label="t('Recovery code')" autocomplete="one-time-code" dir="ltr" :error="form.errors.recovery_code" />
        <Button type="submit" variant="primary" class="w-full" :loading="form.processing">{{ t('Log in') }}</Button>
        <button type="button" class="block w-full text-center text-sm text-muted underline" @click="recovery = !recovery">
            {{ recovery ? t('Use a code from your app') : t('Use a recovery code') }}
        </button>
    </form>
</template>
