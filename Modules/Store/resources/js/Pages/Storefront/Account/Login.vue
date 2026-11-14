<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

import AuthCard from '@/Components/storefront/AuthCard.vue';
import StoreField from '@/Components/storefront/StoreField.vue';
import { btnBrand } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

defineProps<{ status: string | null; staffLoginUrl: string }>();

const form = useForm({ email: '', password: '', remember: false });

function submit(): void {
    form.post(route('store.account.login.store'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <AuthCard :title="t('Sign in')" :intro="t('Follow your orders, keep your addresses and your wishlist.')">
        <p v-if="status" role="status" class="mb-5 rounded-md bg-store-50 px-4 py-3 text-store-800">{{ status }}</p>

        <form class="grid gap-5" novalidate @submit.prevent="submit">
            <StoreField v-model="form.email" :label="t('Email')" type="email" autocomplete="email" dir="ltr" required :maxlength="160" :error="form.errors.email" />
            <StoreField v-model="form.password" :label="t('Password')" type="password" autocomplete="current-password" dir="ltr" required :error="form.errors.password" />
            <p v-if="form.errors.email && form.errors.email === t('Staff accounts with two-factor sign in at the staff login')" class="-mt-2 text-sm">
                <a :href="staffLoginUrl" class="font-bold text-store-700 underline underline-offset-4">{{ t('Go to the staff login') }}</a>
            </p>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex cursor-pointer items-center gap-2">
                    <input v-model="form.remember" type="checkbox" class="size-4 accent-(--color-store)" />
                    <span>{{ t('Remember me') }}</span>
                </label>
                <Link :href="route('store.account.password.request')" class="text-sm font-bold text-store-700 underline-offset-4 hover:underline">{{ t('Forgot your password?') }}</Link>
            </div>

            <button type="submit" :class="btnBrand" :disabled="form.processing">{{ t('Sign in') }}</button>
        </form>

        <template #footer>
            {{ t('New here?') }}
            <Link :href="route('store.account.register')" class="font-bold text-store-700 underline underline-offset-4">{{ t('Create an account') }}</Link>
        </template>
    </AuthCard>
</template>
