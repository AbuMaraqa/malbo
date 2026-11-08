<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{ signUpUrl: string }>();

// The platform's front page: what it is, and the way to open a store.
const features = computed(() => [
    { heading: t('Your own address'), text: t('Every store has its own link, its own colors, font and logo, in Arabic and English.') },
    { heading: t('Ready to sell'), text: t('Products with options and add-ons, offers, categories, and policy pages written for you to adjust.') },
    { heading: t('Orders on WhatsApp'), text: t('Buyers send a complete order with the total; you confirm it in the chat you already use.') },
]);
</script>

<template>
    <PlatformLayout>
        <Head :title="t('Your online store, ready in a minute')" />
        <section class="mx-auto max-w-5xl px-4 py-16 text-center sm:py-24">
            <h1 class="text-4xl leading-tight font-bold text-balance text-gray-900 sm:text-5xl">{{ t('Your online store, ready in a minute') }}</h1>
            <p class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-gray-600">{{ t('Pick a name and an address, add your products, and share your store link. Buyers order on WhatsApp; you manage everything from one dashboard.') }}</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a :href="signUpUrl" class="rounded-md bg-store-600 px-6 py-3 font-semibold text-white hover:bg-store-700">{{ t('Create your store') }}</a>
            </div>
            <p class="mt-3 text-sm text-gray-500">{{ t('Free while we are in the trial period') }}</p>
        </section>

        <section class="mx-auto grid max-w-5xl gap-4 px-4 pb-20 sm:grid-cols-3">
            <div v-for="feature in features" :key="feature.heading" class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="font-semibold text-gray-900">{{ feature.heading }}</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">{{ feature.text }}</p>
            </div>
        </section>
    </PlatformLayout>
</template>
