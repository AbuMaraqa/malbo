<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { ChevronRight } from '@/lib/icons';

defineProps<{ crumbs: { title: string; href: string }[]; current: string }>();
</script>

<template>
    <nav :aria-label="t('Breadcrumb')">
        <ol class="flex flex-wrap items-center gap-x-1.5 text-sm">
            <li v-for="crumb in crumbs" :key="crumb.href" class="flex items-center gap-1.5">
                <Link :href="crumb.href" class="text-gray-500 transition-colors hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400">{{ crumb.title }}</Link>
                <ChevronRight class="size-3.5 text-gray-400 rtl:rotate-180" />
            </li>
            <li class="truncate font-medium text-gray-900 dark:text-white" aria-current="page">{{ current }}</li>
        </ol>
    </nav>
</template>
