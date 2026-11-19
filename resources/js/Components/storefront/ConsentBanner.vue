<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { consentBannerOpen, withdrawMarketing } from '@/lib/consent';
import type { MarketingProps } from '@/lib/metaPixel';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import { btnBrand, btnOutline } from './styles';

/**
 * Asks once whether the store may use marketing cookies (Meta Pixel and its server events). Until
 * the visitor answers, nothing is tracked. Shown only when the store set tracking up, and again from
 * the footer's "Cookie settings": declining then forgets the Pixel's cookies and reloads the page.
 */
const props = defineProps<{ marketing: MarketingProps | null | undefined }>();

const sending = ref(false);
const open = computed(() => props.marketing !== null && props.marketing !== undefined && (props.marketing.consent === null || consentBannerOpen.value));

function answer(choice: 'granted' | 'denied'): void {
    // Consent given before: the Pixel may be running, and only a full reload stops it.
    const withdraw = choice === 'denied' && props.marketing?.consent === 'granted';

    sending.value = true;
    router.post(
        route('store.consent'),
        { choice },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['marketing'],
            onSuccess: () => {
                consentBannerOpen.value = false;
                if (withdraw) withdrawMarketing();
            },
            onFinish: () => (sending.value = false),
        },
    );
}
</script>

<template>
    <div
        v-if="open"
        role="region"
        :aria-label="t('Marketing cookies')"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-(--shell) p-4 shadow-[0_-10px_30px_-18px_rgb(42_21_26/0.35)] sm:inset-x-auto sm:start-4 sm:bottom-4 sm:max-w-md sm:rounded-lg sm:border"
    >
        <p class="font-bold">{{ t('Marketing cookies') }}</p>
        <p class="mt-1 text-sm leading-6 text-(--ink-soft)">
            {{ t('With your permission we use cookies from Meta (Facebook and Instagram) to measure our ads and show you products you may like. The store works the same either way.') }}
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" data-choice="granted" :class="btnBrand" class="min-h-10 px-4 text-sm" :disabled="sending" @click="answer('granted')">
                {{ t('Accept marketing cookies') }}
            </button>
            <button type="button" data-choice="denied" :class="btnOutline" class="min-h-10 px-4 text-sm" :disabled="sending" @click="answer('denied')">
                {{ t('Decline') }}
            </button>
        </div>
    </div>
</template>
