<script setup lang="ts">
import { ExternalLink, Menu, X } from '@/lib/icons';

import { t } from '@/lib/i18n';
import { useSidebar } from '@/tailadmin/composables/useSidebar';
import { Button } from '@/tailadmin/ui/button';

import LocaleSwitcher from './LocaleSwitcher.vue';
import NotificationDropdown from './NotificationDropdown.vue';
import ThemeToggler from './ThemeToggler.vue';
import UserDropdown from './UserDropdown.vue';

const { openMobile, toggleSidebar } = useSidebar();
</script>

<template>
    <header class="sticky top-0 z-50 flex w-full shrink-0 items-center gap-3 border-b border-gray-200 bg-card px-3 py-3 xl:px-6 dark:border-gray-800 dark:bg-gray-900">
        <Button variant="outline" size="icon-lg" class="text-gray-500 dark:text-gray-400" :aria-label="t('Open menu')" @click="toggleSidebar">
            <X v-if="openMobile" />
            <Menu v-else />
        </Button>
        <div class="ms-auto flex items-center gap-2 sm:gap-3">
            <a href="/" target="_blank" rel="noopener" class="hidden items-center gap-1.5 rounded-lg px-2.5 py-2 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700 sm:flex dark:text-gray-400 dark:hover:bg-white/5">
                <ExternalLink class="size-4" />{{ t('View store') }}
            </a>
            <LocaleSwitcher />
            <ThemeToggler />
            <NotificationDropdown />
            <UserDropdown />
        </div>
    </header>
</template>
