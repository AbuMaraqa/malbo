<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { buttonVariants } from '@/tailadmin/ui/button/variants';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
        size?: 'sm' | 'md' | 'icon';
        href?: string;
        external?: boolean;
        type?: 'button' | 'submit';
        loading?: boolean;
        disabled?: boolean;
        ariaLabel?: string;
    }>(),
    { variant: 'secondary', size: 'md', type: 'button' },
);

const classes = computed(() =>
    buttonVariants({
        variant: ({ primary: 'default', secondary: 'outline', ghost: 'ghost', danger: 'destructive' } as const)[props.variant],
        size: ({ sm: 'xs', md: 'default', icon: 'icon-sm' } as const)[props.size],
    }),
);
</script>

<template>
    <a v-if="href && external" :href="href" :class="classes" :aria-label="ariaLabel"><slot /></a>
    <Link v-else-if="href" :href="href" :class="classes" :aria-label="ariaLabel"><slot /></Link>
    <button v-else :type="type" :class="classes" :disabled="disabled || loading" :aria-label="ariaLabel" :aria-busy="loading">
        <slot />
    </button>
</template>
