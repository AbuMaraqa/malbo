<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import AccountShell from '@/Components/storefront/AccountShell.vue';
import StoreField from '@/Components/storefront/StoreField.vue';
import { btnBrand, btnOutline } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{ profile: { name: string; email: string; phone: string | null } }>();

const details = useForm({ name: props.profile.name, email: props.profile.email, phone: props.profile.phone ?? '', current_password: '' });
const password = useForm({ current_password: '', password: '', password_confirmation: '' });

/** A new email needs the current password (the server asks for it too). */
const emailChanged = computed(() => details.email.trim().toLowerCase() !== props.profile.email.toLowerCase());

function saveDetails(): void {
    details.put(route('store.account.profile.update'), { preserveScroll: true, onSuccess: () => details.reset('current_password') });
}

function savePassword(): void {
    password.put(route('store.account.password.change'), { preserveScroll: true, onSuccess: () => password.reset() });
}
</script>

<template>
    <AccountShell :title="t('My account')">
        <section class="rounded-lg border border-line bg-white p-6" aria-labelledby="details-title">
            <h2 id="details-title" class="text-xl font-bold">{{ t('My details') }}</h2>
            <form class="mt-5 grid gap-5 sm:grid-cols-2" novalidate @submit.prevent="saveDetails">
                <StoreField v-model="details.name" class="sm:col-span-2" :label="t('Full name')" autocomplete="name" required :maxlength="120" :error="details.errors.name" />
                <StoreField v-model="details.email" :label="t('Email')" type="email" autocomplete="email" dir="ltr" required :maxlength="160" :error="details.errors.email" />
                <StoreField v-model="details.phone" :label="t('Phone')" type="tel" autocomplete="tel" inputmode="tel" dir="ltr" required :maxlength="40" :error="details.errors.phone" />
                <StoreField
                    v-if="emailChanged"
                    v-model="details.current_password"
                    class="sm:col-span-2"
                    :label="t('Current password')"
                    :hint="t('needed to change the email')"
                    type="password"
                    autocomplete="current-password"
                    dir="ltr"
                    required
                    :error="details.errors.current_password"
                />
                <div class="sm:col-span-2">
                    <button type="submit" :class="btnBrand" :disabled="details.processing">{{ t('Save') }}</button>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-line bg-white p-6" aria-labelledby="password-title">
            <h2 id="password-title" class="text-xl font-bold">{{ t('Change password') }}</h2>
            <form class="mt-5 grid gap-5 sm:grid-cols-2" novalidate @submit.prevent="savePassword">
                <StoreField
                    v-model="password.current_password"
                    class="sm:col-span-2"
                    :label="t('Current password')"
                    type="password"
                    autocomplete="current-password"
                    dir="ltr"
                    required
                    :error="password.errors.current_password"
                />
                <StoreField v-model="password.password" :label="t('New password')" type="password" autocomplete="new-password" dir="ltr" required :error="password.errors.password" />
                <StoreField v-model="password.password_confirmation" :label="t('Confirm password')" type="password" autocomplete="new-password" dir="ltr" required />
                <div class="sm:col-span-2">
                    <button type="submit" :class="btnOutline" :disabled="password.processing">{{ t('Change password') }}</button>
                </div>
            </form>
        </section>
    </AccountShell>
</template>
