<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

import AuthCard from '@/Components/storefront/AuthCard.vue';
import StoreField from '@/Components/storefront/StoreField.vue';
import { btnBrand } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

defineProps<{ status: string | null }>();

const form = useForm({ email: '' });

function submit(): void {
    form.post(route('store.account.password.email'), { preserveScroll: true });
}
</script>

<template>
    <AuthCard :title="t('Forgot your password?')" :intro="t('Enter the email of your account and we will send you a link to choose a new password.')">
        <p v-if="status" role="status" class="mb-5 rounded-md bg-store-50 px-4 py-3 text-store-800">{{ status }}</p>

        <form class="grid gap-5" novalidate @submit.prevent="submit">
            <StoreField v-model="form.email" :label="t('Email')" type="email" autocomplete="email" dir="ltr" required :maxlength="160" :error="form.errors.email" />
            <button type="submit" :class="btnBrand" :disabled="form.processing">{{ t('Send the link') }}</button>
        </form>

        <template #footer>
            <Link :href="route('store.account.login')" class="font-bold text-store-700 underline underline-offset-4">{{ t('Back to sign in') }}</Link>
        </template>
    </AuthCard>
</template>
