<script setup lang="ts">
import { computed } from 'vue';

import { t } from '@/lib/i18n';

/**
 * Stars, shown (a rating, half stars rounded) with the number for screen readers. Pure CSS shapes:
 * no icon font, the same in every direction.
 */
const props = withDefaults(defineProps<{ rating: number; size?: 'sm' | 'md' | 'lg' }>(), { size: 'md' });

const filled = computed(() => Math.round(Math.min(Math.max(props.rating, 0), 5)));
const sizes = { sm: 'text-sm', md: 'text-lg', lg: 'text-2xl' } as const;
</script>

<template>
    <span class="inline-flex items-center leading-none tracking-tight" :class="sizes[size]" role="img" :aria-label="t(':rating out of 5 stars', { rating: rating.toFixed(1) })">
        <span v-for="star in 5" :key="star" aria-hidden="true" :class="star <= filled ? 'text-(--gold)' : 'text-(--ink-18)'">★</span>
    </span>
</template>
