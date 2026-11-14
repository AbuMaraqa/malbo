<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import { fallbackNavIcon, navIcons, type NavEntry } from './navIcons';

defineProps<{ entry: NavEntry; href: string; active: boolean; showText: boolean }>();
</script>

<template>
    <li>
        <Link
            :href="href"
            class="group flex h-10 items-center gap-3 rounded-lg px-2.5 text-sm font-medium transition-colors"
            :class="[
                active ? 'bg-gray-100 text-brand-600 dark:bg-white/5 dark:text-brand-400' : 'text-gray-700 hover:bg-gray-50 hover:text-brand-600 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-brand-400',
                { 'justify-center': !showText },
            ]"
            :title="showText ? undefined : t(entry.title)"
            :aria-label="showText ? undefined : t(entry.title)"
            :aria-current="active ? 'page' : undefined"
        >
            <component
                :is="navIcons[entry.icon] ?? fallbackNavIcon"
                class="size-5 shrink-0 transition-colors"
                :class="active ? 'text-brand-500 dark:text-brand-400' : 'text-gray-400 group-hover:text-brand-500 dark:text-gray-500 dark:group-hover:text-brand-400'"
            />
            <span v-if="showText" class="truncate">{{ t(entry.title) }}</span>
        </Link>
    </li>
</template>
