<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { buttonClasses, type ButtonSize, type ButtonVariant } from '@/lib/buttonClasses';

const props = withDefaults(
    defineProps<{
        variant?: ButtonVariant;
        size?: ButtonSize;
        href?: string;
        external?: boolean;
        /** With `external`: opens in a new tab (a PDF to print). */
        newTab?: boolean;
        type?: 'button' | 'submit';
        loading?: boolean;
        disabled?: boolean;
        ariaLabel?: string;
    }>(),
    { variant: 'secondary', size: 'md', type: 'button' },
);

const classes = computed(() => buttonClasses({ variant: props.variant, size: props.size }));
</script>

<template>
    <a v-if="href && external" :href="href" :class="classes" :aria-label="ariaLabel" :target="newTab ? '_blank' : undefined" :rel="newTab ? 'noopener' : undefined"><slot /></a>
    <Link v-else-if="href" :href="href" :class="classes" :aria-label="ariaLabel"><slot /></Link>
    <button v-else :type="type" :class="classes" :disabled="disabled || loading" :aria-label="ariaLabel" :aria-busy="loading">
        <span v-if="loading" class="size-4 animate-spin rounded-full border-2 border-current border-e-transparent" aria-hidden="true" />
        <slot />
    </button>
</template>
