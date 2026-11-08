<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { t } from '@/lib/i18n';

defineOptions({ layout: GuestLayout });

const props = defineProps<{ email: string; token: string }>();

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
</script>

<template>
    <Head :title="t('Choose a new password')" />
    <h1 class="mb-4 text-base font-semibold">{{ t('Choose a new password') }}</h1>
    <form class="space-y-4" @submit.prevent="form.post('/reset-password')">
        <TextInput v-model="form.email" :label="t('Email')" type="email" autocomplete="username" dir="ltr" required :error="form.errors.email" />
        <TextInput v-model="form.password" :label="t('New password')" type="password" autocomplete="new-password" dir="ltr" required :error="form.errors.password" />
        <TextInput v-model="form.password_confirmation" :label="t('Confirm the password')" type="password" autocomplete="new-password" dir="ltr" required />
        <Button type="submit" variant="primary" class="w-full" :loading="form.processing">{{ t('Save the new password') }}</Button>
    </form>
</template>
