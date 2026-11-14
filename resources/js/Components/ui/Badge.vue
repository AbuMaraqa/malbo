<script setup lang="ts">
withDefaults(
    defineProps<{
        tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand';
        /** A small dot before the text, as a status. */
        dot?: boolean;
    }>(),
    { tone: 'neutral', dot: false },
);

const tones = {
    neutral: 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-800 dark:bg-white/5 dark:text-gray-300',
    success: 'border-success-200 bg-success-50 text-success-700 dark:border-success-500/25 dark:bg-success-500/10 dark:text-success-400',
    warning: 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-500/25 dark:bg-warning-500/10 dark:text-warning-400',
    danger: 'border-error-200 bg-error-50 text-error-700 dark:border-error-500/25 dark:bg-error-500/10 dark:text-error-400',
    info: 'border-blue-light-200 bg-blue-light-50 text-blue-light-700 dark:border-blue-light-500/25 dark:bg-blue-light-500/10 dark:text-blue-light-400',
    brand: 'border-brand-200 bg-brand-50 text-brand-600 dark:border-brand-500/25 dark:bg-brand-500/15 dark:text-brand-400',
} as const;
</script>

<template>
    <span class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs leading-4 font-medium whitespace-nowrap" :class="tones[tone]">
        <span v-if="dot" class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        <slot />
    </span>
</template>
