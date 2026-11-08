<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { ChevronRight } from '@/lib/icons';
import { route } from '@/lib/route';

const props = defineProps<{
    title: string;
    description?: string;
    /** Parents of this page: the current page is `title`, home is added automatically. */
    parents?: { title: string; href: string }[];
}>();

const crumbs = computed(() => [{ title: t('Home'), href: route('dashboard') }, ...(props.parents ?? [])]);
</script>

<template>
    <Head :title="title" />
    <div class="mb-6">
        <!-- Where you are first, then the title: both start at the reading edge of the page. -->
        <nav :aria-label="t('Breadcrumb')" class="mb-2">
            <ol class="flex flex-wrap items-center gap-1 text-sm">
                <li v-for="crumb in crumbs" :key="crumb.href" class="flex items-center gap-1">
                    <Link :href="crumb.href" class="text-gray-500 hover:text-brand-500 dark:text-gray-400">{{ crumb.title }}</Link>
                    <ChevronRight class="size-3.5 text-gray-400 rtl:rotate-180" />
                </li>
                <li class="text-gray-800 dark:text-white/90" aria-current="page">{{ title }}</li>
            </ol>
        </nav>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ title }}</h1>
                <p v-if="description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-3"><slot name="actions" /></div>
        </div>
    </div>
</template>
