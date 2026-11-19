<script setup lang="ts">
import type { StoreBadge } from './types';

/**
 * A product's badges as the server sent them: labels in their own colors, then new, best seller,
 * offer and the stock. `percentOff` adds the saving to the offer badge.
 */
defineProps<{ badges: StoreBadge[]; percentOff?: number | null; size?: 'card' | 'page' }>();

const tones: Record<StoreBadge['tone'], string> = {
    label: 'bg-(--night) text-white',
    accent: 'bg-store text-store-contrast',
    ink: 'bg-(--night) text-white',
    sale: 'bg-(--gold) text-(--night)',
    warning: 'bg-(--shell) text-(--gold-ink) ring-1 ring-(--gold)',
    muted: 'bg-(--shell) text-(--ink-soft) shadow-sm',
};

function style(badge: StoreBadge): Record<string, string> | undefined {
    if (badge.tone !== 'label') return undefined;

    return {
        ...(badge.background_color ? { backgroundColor: badge.background_color } : {}),
        ...(badge.text_color ? { color: badge.text_color } : {}),
    };
}
</script>

<template>
    <ul v-if="badges.length" class="flex flex-wrap gap-1.5">
        <li
            v-for="badge in badges"
            :key="badge.key"
            class="font-semibold ltr:tracking-[0.04em] whitespace-nowrap ltr:uppercase"
            :class="[tones[badge.tone], size === 'page' ? 'px-3 py-1 text-[13px]' : 'px-2 py-0.5 text-xs']"
            :style="style(badge)"
            :data-badge="badge.key"
        >
            {{ badge.label }}
            <span v-if="badge.key === 'offer' && percentOff" dir="ltr">−{{ percentOff }}%</span>
        </li>
    </ul>
</template>
