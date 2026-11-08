<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { t } from '@/lib/i18n';

defineOptions({ layout: GuestLayout });
defineProps<{ status?: string }>();

const form = useForm({ email: '' });
</script>

<template>
    <Head :title="t('Forgot your password?')" />
    <h1 class="mb-2 text-base font-semibold">{{ t('Forgot your password?') }}</h1>
    <p class="mb-4 text-sm text-muted">{{ t('Enter your email and we will send you a link to choose a new password.') }}</p>
    <p v-if="status" class="mb-3 text-sm text-success">{{ status }}</p>
    <form class="space-y-4" @submit.prevent="form.post('/forgot-password')">
        <TextInput v-model="form.email" :label="t('Email')" type="email" autocomplete="username" dir="ltr" required :error="form.errors.email" />
        <Button type="submit" variant="primary" class="w-full" :loading="form.processing">{{ t('Send reset link') }}</Button>
        <p class="text-center text-sm">
            <Link href="/login" class="text-muted underline hover:text-ink">{{ t('Back to log in') }}</Link>
        </p>
    </form>
</template>
