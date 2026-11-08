<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import { t } from '@/lib/i18n';

import type { FontOption } from './signUpTypes';

const props = defineProps<{
    name: string;
    address: string;
    color: string;
    font: FontOption | undefined;
    logo: File | null;
}>();

const logoUrl = ref<string | null>(null);

// Only runs in the browser: `logo` stays null during server rendering.
watch(
    () => props.logo,
    (file) => {
        if (logoUrl.value) {
            URL.revokeObjectURL(logoUrl.value);
        }

        logoUrl.value = file ? URL.createObjectURL(file) : null;
    },
);

onBeforeUnmount(() => {
    if (logoUrl.value) {
        URL.revokeObjectURL(logoUrl.value);
    }
});

const validColor = computed(() => (/^#[0-9a-f]{6}$/i.test(props.color) ? props.color : '#1F6F50'));

/** White or dark text, whichever reads on the chosen color. Same idea as BrandColor::onBrand. */
const onColor = computed(() => {
    const [r, g, b] = [1, 3, 5].map((start) => parseInt(validColor.value.slice(start, start + 2), 16));

    return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.6 ? '#111827' : '#FFFFFF';
});
</script>

<template>
    <aside class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" :style="{ fontFamily: font?.stack }" :aria-label="t('Preview')">
        <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-3">
            <img v-if="logoUrl" :src="logoUrl" alt="" class="size-8 rounded object-contain" />
            <span class="truncate font-semibold">{{ name || t('Store name') }}</span>
        </div>
        <div class="px-4 py-6">
            <p class="text-lg font-bold">{{ t('Your online store, ready in a minute') }}</p>
            <p class="mt-2 text-sm text-gray-600">{{ t('Your own colors, font, logo and texts') }}</p>
            <span class="mt-4 inline-block rounded-md px-4 py-2 text-sm font-semibold" :style="{ backgroundColor: validColor, color: onColor }">{{ t('Order on WhatsApp') }}</span>
        </div>
        <p class="truncate border-t border-gray-100 bg-gray-50 px-4 py-2 text-xs text-gray-500" dir="ltr">{{ address }}</p>
    </aside>
</template>
