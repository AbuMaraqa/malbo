<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { DropdownMenuItem } from 'reka-ui';

/** One line of a DropdownMenu: a page link (`href`), an outside link (`external`) or an action (`select`). */
defineProps<{ href?: string; external?: boolean; destructive?: boolean }>();

const emit = defineEmits<{ select: [event: Event] }>();

const classes =
    'flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-start font-medium outline-none select-none [&_svg]:size-[18px] [&_svg]:shrink-0';
</script>

<template>
    <DropdownMenuItem
        :as-child="!!href"
        :class="[classes, destructive ? 'text-error-600 data-highlighted:bg-error-50 dark:text-error-400 dark:data-highlighted:bg-error-500/10' : 'text-gray-700 data-highlighted:bg-gray-100 data-highlighted:text-gray-900 dark:text-gray-300 dark:data-highlighted:bg-white/5 dark:data-highlighted:text-white [&_svg]:text-gray-500 dark:[&_svg]:text-gray-400']"
        @select="emit('select', $event)"
    >
        <a v-if="href && external" :href="href" target="_blank" rel="noopener"><slot /></a>
        <Link v-else-if="href" :href="href"><slot /></Link>
        <slot v-else />
    </DropdownMenuItem>
</template>
