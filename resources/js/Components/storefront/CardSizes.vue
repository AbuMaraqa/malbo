<script setup lang="ts">
import { t } from '@/lib/i18n';

import type { CardSize } from './types';

/** A card's sizes in one compact line; one no variant can be ordered in is struck through. */
defineProps<{ sizes: CardSize[] }>();
</script>

<template>
    <ul v-if="sizes.length" class="flex flex-wrap gap-1" :aria-label="t('Sizes')">
        <li
            v-for="size in sizes"
            :key="size.name"
            class="min-w-7 rounded border px-1.5 py-0.5 text-center text-[0.75rem] leading-4"
            :class="size.available ? 'border-line text-(--ink-soft)' : 'border-transparent text-muted line-through opacity-70'"
        >
            {{ size.name }}<span v-if="!size.available" class="sr-only"> ({{ t('Sold out') }})</span>
        </li>
    </ul>
</template>
