<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import AuthCard from '@/Components/storefront/AuthCard.vue';
import StoreField from '@/Components/storefront/StoreField.vue';
import { btnBrand } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{ token: string; email: string }>();

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });

function submit(): void {
    form.post(route('store.account.password.update'), { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <AuthCard :title="t('Choose a new password')">
        <form class="grid gap-5" novalidate @submit.prevent="submit">
            <StoreField v-model="form.email" :label="t('Email')" type="email" autocomplete="email" dir="ltr" required :maxlength="160" :error="form.errors.email" />
            <StoreField v-model="form.password" :label="t('New password')" type="password" autocomplete="new-password" dir="ltr" required :error="form.errors.password" />
            <StoreField v-model="form.password_confirmation" :label="t('Confirm password')" type="password" autocomplete="new-password" dir="ltr" required />
            <button type="submit" :class="btnBrand" :disabled="form.processing">{{ t('Save the new password') }}</button>
        </form>
    </AuthCard>
</template>
