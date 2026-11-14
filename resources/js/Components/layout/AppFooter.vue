<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';

const page = usePage();
const name = computed(() => (page.props.store as { name: string } | null)?.name ?? '');
const year = new Date().getFullYear();
</script>

<template>
    <footer class="mx-auto flex w-full max-w-[1440px] flex-wrap items-center justify-between gap-2 px-4 py-5 text-sm text-muted md:px-6 lg:px-8">
        <p>
            <span class="tabular">{{ year }}</span> &copy;
            <span class="font-medium text-gray-700 dark:text-gray-300">{{ name }}</span>
        </p>
        <nav class="flex items-center gap-4" :aria-label="t('Footer')">
            <a href="/" target="_blank" rel="noopener" class="hover:text-brand-600 dark:hover:text-brand-400">{{ t('View store') }}</a>
            <a :href="route('profile.index')" class="hover:text-brand-600 dark:hover:text-brand-400">{{ t('Profile') }}</a>
            <a v-if="can('Setting View List')" :href="route('settings')" class="hover:text-brand-600 dark:hover:text-brand-400">{{ t('System Settings') }}</a>
        </nav>
    </footer>
</template>
