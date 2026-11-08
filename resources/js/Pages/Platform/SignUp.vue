<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { t } from '@/lib/i18n';

import SignUpStepper from './parts/SignUpStepper.vue';
import StepBranding from './parts/StepBranding.vue';
import StepBusiness from './parts/StepBusiness.vue';
import StepOwner from './parts/StepOwner.vue';
import StepReview from './parts/StepReview.vue';
import StorePreview from './parts/StorePreview.vue';
import type { BusinessType, FontOption, SignUpData, SlugState } from './parts/signUpTypes';

const props = defineProps<{
    baseDomain: string;
    businessTypes: BusinessType[];
    fonts: FontOption[];
    colors: string[];
    defaults: { color: string; font: string };
    storeUrl: string;
    slugUrl: string;
}>();

const form = useForm<SignUpData>({
    storeName: '',
    slug: '',
    businessType: null,
    ownerName: '',
    ownerEmail: '',
    ownerPhone: '',
    password: '',
    password_confirmation: '',
    color: props.defaults.color,
    font: props.defaults.font,
    logo: null,
    terms: false,
    website: '',
});

const steps = computed(() => [t('Your store'), t('Your account'), t('Look'), t('Review')]);
const step = ref(0);

/** Which step shows each field, to return there when the server rejects it. */
const STEP_OF_FIELD: Record<string, number> = {
    storeName: 0,
    slug: 0,
    businessType: 0,
    ownerName: 1,
    ownerEmail: 1,
    ownerPhone: 1,
    password: 1,
    logo: 2,
    color: 2,
    font: 2,
};

const canContinue = computed(() => {
    if (step.value === 0) {
        return form.storeName.trim() !== '' && slugState.value === 'available';
    }

    if (step.value === 1) {
        return form.ownerName.trim() !== '' && form.ownerEmail.trim() !== '' && form.password.length >= 8 && form.password === form.password_confirmation;
    }

    return true;
});

const slugState = ref<SlugState>('idle');
const slugMessage = ref<string | null>(null);
let slugTimer: ReturnType<typeof setTimeout> | undefined;
let slugRequest: AbortController | undefined;

function onSlugInput(value: string): void {
    form.slug = value.trim().toLowerCase();
    form.clearErrors('slug');
    clearTimeout(slugTimer);
    slugRequest?.abort();

    if (form.slug === '') {
        slugState.value = 'idle';
        slugMessage.value = null;

        return;
    }

    slugState.value = 'checking';
    slugTimer = setTimeout(checkSlug, 500);
}

async function checkSlug(): Promise<void> {
    slugRequest = new AbortController();

    try {
        const response = await fetch(`${props.slugUrl}?slug=${encodeURIComponent(form.slug)}`, {
            headers: { Accept: 'application/json' },
            signal: slugRequest.signal,
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const result = (await response.json()) as { available: boolean; message: string | null };

        slugState.value = result.available ? 'available' : 'unavailable';
        slugMessage.value = result.message;
    } catch (error) {
        if ((error as Error).name !== 'AbortError') {
            // The check is a convenience, the server checks again on submit: never block the visitor on it.
            slugState.value = 'available';
            slugMessage.value = null;
        }
    }
}

onBeforeUnmount(() => clearTimeout(slugTimer));

// Not a field of the form: the server sends it when creating the store fails or the limit is reached.
const provisioningError = computed(() => (form.errors as Record<string, string | undefined>).provisioning);

const font = computed(() => props.fonts.find((option) => option.value === form.font));
const address = computed(() => `${form.slug || 'yourname'}.${props.baseDomain}`);
const businessLabel = computed(() => props.businessTypes.find((type) => type.value === form.businessType)?.label ?? '—');

// Only on the client: the fonts of the preview are loaded when chosen.
watch(
    () => font.value?.url,
    (url) => {
        if (url && !document.querySelector(`link[href="${url}"]`)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = url;
            document.head.append(link);
        }
    },
);

function submit(): void {
    form.clearErrors();
    form.post(props.storeUrl, {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => {
            const steps = Object.keys(errors).map((field) => STEP_OF_FIELD[field] ?? 3);
            step.value = Math.min(...steps);
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}

function next(): void {
    if (step.value < 3 && canContinue.value) {
        step.value += 1;
    }
}
</script>

<template>
    <PlatformLayout>
        <Head :title="t('Create your online store')" />
        <div class="mx-auto grid w-full max-w-5xl gap-10 px-4 py-12 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <h1 class="text-3xl font-bold text-gray-900">{{ t('Create your online store') }}</h1>
                <p class="mt-3 leading-7 text-gray-600">{{ t('Your own store on its own address, in Arabic and English, ready in a minute. Add your products, choose your colors, and start taking orders on WhatsApp.') }}</p>
                <ul class="mt-6 space-y-3 text-sm text-gray-700">
                    <li>{{ t('Your store address, for example yourname.:domain', { domain: baseDomain }) }}</li>
                    <li>{{ t('Ready policy pages: privacy, returns, shipping and terms') }}</li>
                    <li>{{ t('Your own colors, font, logo and texts') }}</li>
                    <li>{{ t('Free while we are in the trial period') }}</li>
                </ul>
                <StorePreview class="mt-8" :name="form.storeName" :address="address" :color="form.color" :font="font" :logo="form.logo" />
            </div>

            <form class="relative space-y-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm lg:col-span-3" @submit.prevent="step === 3 ? submit() : next()">
                <SignUpStepper :steps="steps" :current="step" />

                <StepBusiness v-show="step === 0" :form="form" :base-domain="baseDomain" :business-types="businessTypes" :slug-state="slugState" :slug-message="slugMessage" @slug-input="onSlugInput" />
                <StepOwner v-show="step === 1" :form="form" />
                <StepBranding v-show="step === 2" :form="form" :fonts="fonts" :colors="colors" />
                <StepReview v-show="step === 3" :form="form" :address="address" :business-label="businessLabel" :font-label="font?.label ?? ''" />

                <!-- Bots fill every field; people never see this one. -->
                <div aria-hidden="true" class="absolute -start-[9999px] h-px w-px overflow-hidden">
                    <label for="website">Website</label>
                    <input id="website" v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
                </div>

                <p v-if="provisioningError" class="rounded-md bg-danger-50 px-3 py-2 text-sm text-danger" role="alert">{{ provisioningError }}</p>

                <div class="flex items-center justify-between gap-3">
                    <button v-if="step > 0" type="button" class="rounded-md border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" :disabled="form.processing" @click="step -= 1">{{ t('Back') }}</button>
                    <span v-else />
                    <button v-if="step < 3" type="submit" class="rounded-md bg-store-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-store-700 disabled:opacity-60" :disabled="!canContinue">{{ t('Continue') }}</button>
                    <button v-else type="submit" class="rounded-md bg-store-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-store-700 disabled:opacity-60" :disabled="form.processing || !form.terms">
                        {{ form.processing ? t('Setting up your store…') : t('Create my store') }}
                    </button>
                </div>
            </form>
        </div>
    </PlatformLayout>
</template>
