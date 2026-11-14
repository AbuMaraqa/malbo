<script setup lang="ts">
import { ref, watch } from 'vue';

import { t } from '@/lib/i18n';
import { ChevronsStart } from '@/lib/icons';
import { useSidebar } from '@/lib/useSidebar';

import SidebarBrand from './SidebarBrand.vue';
import SidebarLink from './SidebarLink.vue';
import SidebarLinkGroup from './SidebarLinkGroup.vue';
import { useNavigation } from './useNavigation';

const { collapsed, mobileOpen, isMobile, hovered, showText, toggle, closeMobile } = useSidebar();
const { entries, path, hrefOf, isActive } = useNavigation();

const groupWithActiveChild = (): string | null => entries.value.find((entry) => (entry.children ?? []).some(isActive))?.title ?? null;

/** One group open at a time: the one holding the page, until another is opened. */
const openGroup = ref<string | null>(groupWithActiveChild());

watch(path, () => (openGroup.value = groupWithActiveChild()));

function toggleGroup(title: string): void {
    openGroup.value = openGroup.value === title ? null : title;
}

/** Collapsed on a desktop: the sidebar opens over the page while the pointer is on it. */
function hover(value: boolean): void {
    if (collapsed.value && !isMobile.value) hovered.value = value;
}
</script>

<template>
    <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition-opacity" leave-active-class="transition-opacity">
        <div v-if="isMobile && mobileOpen" class="fixed inset-0 z-[55] bg-gray-950/30 backdrop-blur-[2px] lg:hidden" aria-hidden="true" @click="closeMobile" />
    </Transition>

    <aside
        class="fixed inset-y-0 start-0 z-[60] flex w-sidebar flex-col border-e border-line bg-card transition-[width,translate,box-shadow] duration-200 ease-out"
        :class="{
            'lg:w-sidebar-collapsed': collapsed && !hovered,
            'shadow-theme-xl': hovered || (isMobile && mobileOpen),
            'max-lg:ltr:-translate-x-full max-lg:rtl:translate-x-full': !mobileOpen,
        }"
        :aria-label="t('Main menu')"
        @mouseenter="hover(true)"
        @mouseleave="hover(false)"
    >
        <div class="relative flex h-header shrink-0 items-center px-5" :class="{ 'lg:justify-center lg:px-0': !showText }">
            <SidebarBrand :show-text="showText" />

            <button
                type="button"
                class="absolute top-1/2 -end-3.5 hidden size-7 -translate-y-1/2 items-center justify-center rounded-lg border border-line bg-card text-gray-500 shadow-theme-xs hover:text-brand-600 lg:flex dark:text-gray-400 dark:hover:text-brand-400"
                :aria-label="collapsed ? t('Expand sidebar') : t('Collapse sidebar')"
                :aria-expanded="!collapsed"
                :title="`${collapsed ? t('Expand sidebar') : t('Collapse sidebar')} (Ctrl+B)`"
                @click="toggle"
            >
                <!-- Points to the start edge to fold, to the end edge to unfold, in either direction. -->
                <ChevronsStart class="size-4 transition-transform" :class="collapsed ? 'rotate-180 rtl:rotate-0' : 'rtl:rotate-180'" />
            </button>
        </div>

        <nav class="custom-scrollbar flex-1 overflow-x-hidden overflow-y-auto px-3 pt-2 pb-6">
            <ul class="flex flex-col gap-0.5">
                <template v-for="(entry, index) in entries" :key="entry.title">
                    <!-- A section's heading, before its first entry: "Sales", "Catalog"... -->
                    <li
                        v-if="entry.section && entry.section !== entries[index - 1]?.section"
                        class="mt-4 mb-1 flex h-6 items-center px-2.5 text-xs font-medium text-gray-400 first:mt-0 dark:text-gray-500"
                        :class="{ 'justify-center': !showText }"
                        role="presentation"
                    >
                        <template v-if="showText">{{ t(entry.section) }}</template>
                        <span v-else class="h-px w-5 bg-line" aria-hidden="true" />
                    </li>
                    <SidebarLink v-if="entry.type === 'item'" :entry="entry" :href="hrefOf(entry)" :active="isActive(entry)" :show-text="showText" />
                    <SidebarLinkGroup
                        v-else
                        :entry="entry"
                        :open="openGroup === entry.title"
                        :show-text="showText"
                        :href-of="hrefOf"
                        :is-active="isActive"
                        @toggle="toggleGroup(entry.title)"
                    />
                </template>
            </ul>
        </nav>
    </aside>
</template>
