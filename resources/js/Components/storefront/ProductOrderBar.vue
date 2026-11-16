<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import { btnBrand, btnOutline } from './styles';

/**
 * The total, "Add to cart" and "Order on WhatsApp": a sticky bar on phones, inline on desktop.
 * The cart is the main action when the product can go in it; WhatsApp stays as a second way.
 */
defineProps<{
    total: string;
    updating: boolean;
    hasWhatsapp: boolean;
    whatsappUrl: string | null;
    canAddToCart: boolean;
    /** The chosen item can be added now (a complete, available choice). */
    ready: boolean;
    adding: boolean;
    added: boolean;
    cartUrl: string;
}>();

const emit = defineEmits<{ add: [] }>();
</script>

<template>
    <div data-order-bar class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white px-4 py-3 shadow-[0_-8px_24px_-16px_rgb(42_21_26/0.3)] lg:static lg:shadow-none lg:mt-8 lg:border-0 lg:bg-transparent lg:p-0">
        <div class="mx-auto flex max-w-310 flex-wrap items-center gap-x-4 gap-y-2">
            <p class="shrink-0">
                <span class="block text-xs text-muted">{{ t('Total') }}</span>
                <span class="text-xl font-bold transition-opacity" :class="{ 'opacity-60': updating }">{{ total }}</span>
            </p>
            <button
                v-if="canAddToCart"
                type="button"
                class="flex-1"
                :class="btnBrand"
                :disabled="!ready || updating || adding"
                aria-describedby="order-problems"
                @click="emit('add')"
            >
                {{ adding ? t('Adding…') : t('Add to cart') }}
            </button>
            <template v-if="hasWhatsapp">
                <a v-if="whatsappUrl && !updating" :href="whatsappUrl" target="_blank" rel="noopener" :class="[canAddToCart ? btnOutline : btnBrand, canAddToCart ? 'max-lg:px-4' : 'flex-1']">
                    {{ canAddToCart ? t('WhatsApp') : t('Order on WhatsApp') }}
                </a>
                <button v-else type="button" disabled aria-describedby="order-problems" :class="[canAddToCart ? btnOutline : btnBrand, canAddToCart ? 'max-lg:px-4' : 'flex-1']">
                    {{ canAddToCart ? t('WhatsApp') : t('Order on WhatsApp') }}
                </button>
            </template>
            <p v-if="added" role="status" class="basis-full text-sm font-bold">
                {{ t('Added to your cart.') }}
                <Link :href="cartUrl" class="text-store-600 underline underline-offset-4">{{ t('View cart') }}</Link>
            </p>
        </div>
    </div>
</template>
