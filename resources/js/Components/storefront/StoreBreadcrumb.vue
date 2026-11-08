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
            <li><Link :href="route('home')" class="hover:text-ink">{{ t('Home') }}</Link></li>
            <li aria-hidden="true">/</li>
            <template v-for="parent in parents" :key="parent.url">
                <li><Link :href="parent.url" class="hover:text-ink">{{ parent.name }}</Link></li>
                <li aria-hidden="true">/</li>
            </template>
            <li aria-current="page" class="text-ink">{{ title }}</li>
        </ol>
    </nav>
</template>
