<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

/** Home, then any parents, then the current page's title. */
defineProps<{ title: string; parents?: { name: string; url: string }[] }>();
</script>

<template>
    <nav :aria-label="t('Breadcrumb')" class="text-sm text-muted">
        <ol class="flex flex-wrap items-center gap-2">
            <li><Link :href="route('home')" class="font-medium hover:text-store-600">{{ t('Home') }}</Link></li>
            <li aria-hidden="true" class="text-(--ink-30)">/</li>
            <template v-for="parent in parents" :key="parent.url">
                <li><Link :href="parent.url" class="font-medium hover:text-store-600">{{ parent.name }}</Link></li>
                <li aria-hidden="true" class="text-(--ink-30)">/</li>
            </template>
            <li aria-current="page" class="max-w-60 truncate text-store-600">{{ title }}</li>
        </ol>
    </nav>
</template>
