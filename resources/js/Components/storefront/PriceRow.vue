<script setup lang="ts">
import { t } from '@/lib/i18n';

import type { StorePrice } from './types';

/** A price and, when it is discounted, the crossed-out one. Both texts come from the server. */
defineProps<{ price: StorePrice; size?: 'card' | 'large' }>();
</script>

<template>
    <p class="flex flex-wrap items-baseline" :class="size === 'large' ? 'gap-3' : 'gap-2'">
        <span class="font-bold text-store-700" :class="size === 'large' ? 'text-2xl' : 'text-lg'">{{ price.label }}</span>
        <s v-if="price.discounted" class="text-muted" :class="{ 'text-sm': size !== 'large' }">
            <span class="sr-only">{{ t('Was') }}</span> {{ price.was }}
        </s>
        <slot />
    </p>
</template>
