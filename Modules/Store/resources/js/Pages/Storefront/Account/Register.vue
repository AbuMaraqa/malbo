<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

import AuthCard from '@/Components/storefront/AuthCard.vue';
import StoreField from '@/Components/storefront/StoreField.vue';
import { btnBrand } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

const form = useForm({ name: '', phone: '', email: '', password: '', password_confirmation: '' });

function submit(): void {
    form.post(route('store.account.register.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <AuthCard :title="t('Create an account')" :intro="t('Order faster, follow your orders and keep a wishlist.')">
        <form class="grid gap-5" novalidate @submit.prevent="submit">
            <StoreField v-model="form.name" :label="t('Full name')" autocomplete="name" required :maxlength="120" :error="form.errors.name" />
            <StoreField v-model="form.phone" :label="t('Phone')" type="tel" autocomplete="tel" inputmode="tel" dir="ltr" required :maxlength="40" :error="form.errors.phone" />
            <StoreField v-model="form.email" :label="t('Email')" type="email" autocomplete="email" dir="ltr" required :maxlength="160" :error="form.errors.email" />
            <StoreField v-model="form.password" :label="t('Password')" type="password" autocomplete="new-password" dir="ltr" required :error="form.errors.password" />
            <StoreField v-model="form.password_confirmation" :label="t('Confirm password')" type="password" autocomplete="new-password" dir="ltr" required />

            <button type="submit" :class="btnBrand" :disabled="form.processing">{{ t('Create an account') }}</button>
        </form>

        <template #footer>
            {{ t('Already have an account?') }}
            <Link :href="route('store.account.login')" class="font-bold text-store-700 underline underline-offset-4">{{ t('Sign in') }}</Link>
        </template>
    </AuthCard>
</template>
