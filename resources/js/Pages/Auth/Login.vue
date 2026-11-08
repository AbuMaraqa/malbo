<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Button from '@/Components/ui/Button.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { t } from '@/lib/i18n';

defineOptions({ layout: GuestLayout });
defineProps<{ status?: string }>();

const form = useForm({ email: '', password: '', remember: false });

function submit(): void {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head :title="t('Log in')" />
    <h1 class="mb-4 text-base font-semibold">{{ t('Log in') }}</h1>
    <p v-if="status" class="mb-3 text-sm text-success">{{ status }}</p>
    <form class="space-y-4" @submit.prevent="submit">
        <TextInput v-model="form.email" :label="t('Email')" type="email" autocomplete="username" dir="ltr" required :error="form.errors.email" />
        <TextInput v-model="form.password" :label="t('Password')" type="password" autocomplete="current-password" dir="ltr" required :error="form.errors.password" />
        <Toggle v-model="form.remember" :label="t('Remember me')" />
        <Button type="submit" variant="primary" class="w-full" :loading="form.processing">{{ t('Log in') }}</Button>
        <p class="text-center text-sm">
            <Link href="/forgot-password" class="text-muted underline hover:text-ink">{{ t('Forgot your password?') }}</Link>
        </p>
    </form>
</template>
