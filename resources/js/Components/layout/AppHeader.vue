<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

import { buttonClasses } from '@/lib/buttonClasses';
import { t } from '@/lib/i18n';
import { ExternalLink, Menu, Search } from '@/lib/icons';
import { useSidebar } from '@/lib/useSidebar';

import AppsMenu from './AppsMenu.vue';
import CommandSearch from './CommandSearch.vue';
import NotificationsButton from './NotificationsButton.vue';
import SidebarBrand from './SidebarBrand.vue';
import UserDropdown from './UserDropdown.vue';

const { toggle } = useSidebar();

const searchOpen = ref(false);
/** The header gets its border once the page scrolls under it. */
const scrolled = ref(false);

const onScroll = (): void => {
    scrolled.value = window.scrollY > 4;
};

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onUnmounted(() => window.removeEventListener('scroll', onScroll));

const iconButton = buttonClasses({ variant: 'ghost', size: 'icon-md', class: 'rounded-full text-gray-500 dark:text-gray-400' });
</script>

<template>
    <header
        class="sticky top-0 z-40 flex h-header shrink-0 items-center gap-3 border-b px-4 transition-[background-color,border-color,box-shadow] md:px-6 lg:px-8"
        :class="scrolled ? 'border-line bg-card/85 shadow-theme-xs backdrop-blur-md' : 'border-transparent bg-canvas'"
    >
        <button type="button" :class="buttonClasses({ variant: 'secondary', size: 'icon-md', class: 'lg:hidden' })" :aria-label="t('Open menu')" @click="toggle">
            <Menu />
        </button>
        <div class="lg:hidden"><SidebarBrand :show-text="false" /></div>

        <!-- The page's breadcrumb lands here (PageHeader teleports it). -->
        <div id="header-breadcrumbs" class="hidden min-w-0 lg:block" />

        <div class="ms-auto flex items-center gap-1 sm:gap-1.5">
            <!-- Looks like a field, opens the search dialog: pages and actions by name. -->
            <button
                type="button"
                class="me-1.5 hidden h-10 w-60 items-center gap-2.5 rounded-lg border border-line bg-card px-3 text-sm text-gray-400 shadow-theme-xs transition-colors hover:border-gray-300 xl:flex dark:text-gray-500 dark:hover:border-gray-700"
                :aria-label="t('Search')"
                @click="searchOpen = true"
            >
                <Search class="size-4.5" />
                <span class="flex-1 text-start">{{ t('Search...') }}</span>
                <span class="flex items-center gap-1" dir="ltr"><kbd class="kbd">Ctrl</kbd><kbd class="kbd">K</kbd></span>
            </button>
            <button type="button" :class="[iconButton, 'xl:hidden']" :aria-label="t('Search')" title="Ctrl+K" @click="searchOpen = true"><Search /></button>
            <a href="/" target="_blank" rel="noopener" :class="iconButton" :title="t('View store')" :aria-label="t('View store')"><ExternalLink /></a>
            <NotificationsButton :button-class="iconButton" />
            <AppsMenu :button-class="iconButton" />
            <span class="mx-1 hidden h-6 w-px bg-line sm:block" aria-hidden="true" />
            <UserDropdown />
        </div>

        <CommandSearch v-model:open="searchOpen" />
    </header>
</template>
