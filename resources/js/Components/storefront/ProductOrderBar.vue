<script setup lang="ts">
import { t } from '@/lib/i18n';

import { btnBrand } from './styles';

/** The total and "Order on WhatsApp": a sticky bar on phones, inline on desktop. */
defineProps<{ total: string; updating: boolean; hasWhatsapp: boolean; whatsappUrl: string | null }>();
</script>

<template>
    <div data-order-bar class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white/95 px-4 py-3 backdrop-blur lg:static lg:mt-8 lg:border-0 lg:bg-transparent lg:p-0 lg:backdrop-blur-none">
        <div class="mx-auto flex max-w-310 items-center gap-4">
            <p class="shrink-0">
                <span class="block text-xs text-muted">{{ t('Total') }}</span>
                <span class="text-xl font-bold transition-opacity" :class="{ 'opacity-60': updating }">{{ total }}</span>
            </p>
            <template v-if="hasWhatsapp">
                <a v-if="whatsappUrl && !updating" :href="whatsappUrl" target="_blank" rel="noopener" class="flex-1" :class="btnBrand">{{ t('Order on WhatsApp') }}</a>
                <button v-else type="button" disabled aria-describedby="order-problems" class="flex-1" :class="btnBrand">{{ t('Order on WhatsApp') }}</button>
            </template>
        </div>
    </div>
</template>
