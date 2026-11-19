<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

/** Home, then any parents, then the current page's title. `onDark` for the maroon page banner. */
defineProps<{ title: string; parents?: { name: string; url: string }[]; onDark?: boolean }>();
</script>

<template>
    <nav :aria-label="t('Breadcrumb')" class="text-sm" :class="onDark ? 'text-white/80' : 'text-muted'">
        <ol class="flex flex-wrap items-center gap-2" :class="{ 'justify-center': onDark }">
            <li><Link :href="route('home')" class="font-medium" :class="onDark ? 'hover:text-white' : 'hover:text-store-600'">{{ t('Home') }}</Link></li>
            <li aria-hidden="true" :class="onDark ? 'text-white/40' : 'text-(--ink-30)'">/</li>
            <template v-for="parent in parents" :key="parent.url">
                <li><Link :href="parent.url" class="font-medium" :class="onDark ? 'hover:text-white' : 'hover:text-store-600'">{{ parent.name }}</Link></li>
                <li aria-hidden="true" :class="onDark ? 'text-white/40' : 'text-(--ink-30)'">/</li>
            </template>
            <li aria-current="page" class="max-w-60 truncate" :class="onDark ? 'text-(--gold)' : 'text-store-600'">{{ title }}</li>
        </ol>
    </nav>
</template>
