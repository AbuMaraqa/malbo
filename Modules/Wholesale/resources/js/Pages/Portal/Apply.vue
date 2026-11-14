<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { useId } from 'vue';

import AuthCard from '@/Components/storefront/AuthCard.vue';
import StoreField from '@/Components/storefront/StoreField.vue';
import StoreSelect from '@/Components/storefront/StoreSelect.vue';
import { btnBrand, field, fieldError, fieldLabel } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

defineProps<{ cities: { value: number; label: string }[] }>();

const form = useForm<{
    shop_name: string;
    owner_name: string;
    city_id: number | null;
    phone: string;
    email: string;
    commercial_register: string;
    shop_address: string;
    register_document: File | null;
    password: string;
    password_confirmation: string;
}>({
    shop_name: '',
    owner_name: '',
    city_id: null,
    phone: '',
    email: '',
    commercial_register: '',
    shop_address: '',
    register_document: null,
    password: '',
    password_confirmation: '',
});

const documentId = useId();

function pick(event: Event): void {
    form.register_document = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submit(): void {
    form.post(route('store.wholesale.apply.store'), { forceFormData: true, onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <AuthCard :title="t('Become a wholesale merchant')" :intro="t('Shops buy MALBO at wholesale prices. Tell us about your shop: we review every application and let you know.')">
        <form class="grid gap-5" novalidate @submit.prevent="submit">
            <StoreField v-model="form.shop_name" :label="t('Shop name')" required :maxlength="160" :error="form.errors.shop_name" />
            <StoreField v-model="form.owner_name" :label="t('Owner\'s full name')" autocomplete="name" required :maxlength="120" :error="form.errors.owner_name" />
            <StoreSelect v-model="form.city_id" :label="t('City')" :options="cities" :placeholder="t('Choose a city')" required :error="form.errors.city_id" />
            <StoreField v-model="form.shop_address" :label="t('Shop address')" :hint="t('optional')" :rows="2" :maxlength="500" :error="form.errors.shop_address" />
            <StoreField v-model="form.phone" :label="t('Phone')" type="tel" autocomplete="tel" inputmode="tel" dir="ltr" required :maxlength="40" :error="form.errors.phone" />
            <StoreField v-model="form.email" :label="t('Email')" type="email" autocomplete="email" dir="ltr" required :maxlength="160" :error="form.errors.email" />
            <StoreField v-model="form.commercial_register" :label="t('Commercial register number')" dir="ltr" required :maxlength="60" :error="form.errors.commercial_register" />

            <div>
                <label :for="documentId" :class="fieldLabel">
                    {{ t('Commercial register document') }}
                    <span class="font-normal text-muted">({{ t('optional: PDF, JPG or PNG, up to 5 MB') }})</span>
                </label>
                <input
                    :id="documentId"
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                    :aria-invalid="form.errors.register_document ? true : undefined"
                    :class="field"
                    class="py-2"
                    @change="pick"
                />
                <p v-if="form.errors.register_document" :class="fieldError">{{ form.errors.register_document }}</p>
            </div>

            <StoreField v-model="form.password" :label="t('Password')" type="password" autocomplete="new-password" dir="ltr" required :error="form.errors.password" />
            <StoreField v-model="form.password_confirmation" :label="t('Confirm password')" type="password" autocomplete="new-password" dir="ltr" required />

            <button type="submit" :class="btnBrand" :disabled="form.processing">{{ t('Send my application') }}</button>
        </form>

        <template #footer>
            {{ t('Already have an account?') }}
            <Link :href="route('store.account.login')" class="font-bold text-store-700 underline underline-offset-4">{{ t('Sign in') }}</Link>
        </template>
    </AuthCard>
</template>
