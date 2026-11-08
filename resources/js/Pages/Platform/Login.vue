<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{ loginUrl: string }>();

const form = useForm({ email: '', password: '', remember: false });

function submit(): void {
    form.post(props.loginUrl, { onFinish: () => form.reset('password') });
}
</script>

<template>
    <PlatformLayout admin>
        <Head :title="t('Platform sign-in')" />
        <div class="mx-auto mt-16 w-full max-w-sm px-4">
            <div class="rounded-2xl border border-line bg-card p-6">
                <h1 class="text-xl font-semibold text-gray-800">{{ t('Platform sign-in') }}</h1>
                <p class="mt-1 text-sm text-muted">{{ t('For the people who run the platform. Store staff sign in on their store.') }}</p>

                <form class="mt-6 space-y-5" @submit.prevent="submit">
                    <TextInput v-model="form.email" :label="t('Email')" type="email" autocomplete="username" dir="ltr" required :error="form.errors.email" />
                    <TextInput v-model="form.password" :label="t('Password')" type="password" autocomplete="current-password" dir="ltr" required :error="form.errors.password" />

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.remember" type="checkbox" class="accent-brand" />
                        {{ t('Keep me signed in') }}
                    </label>

                    <div class="flex">
                        <Button variant="primary" type="submit" class="w-full" :loading="form.processing">{{ t('Sign in') }}</Button>
                    </div>
                </form>
            </div>
        </div>
    </PlatformLayout>
</template>
