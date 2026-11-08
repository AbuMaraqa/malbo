<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { t } from '@/lib/i18n';
import { MoreHorizontal, Store } from '@/lib/icons';
import { route } from '@/lib/route';
import { useSidebar } from '@/tailadmin/composables/useSidebar';
import { Sidebar, SidebarContent, SidebarGroup, SidebarGroupLabel, SidebarHeader, SidebarMenu } from '@/tailadmin/ui/sidebar';

import SidebarLink from './SidebarLink.vue';
import SidebarLinkGroup from './SidebarLinkGroup.vue';
import type { NavEntry } from './navIcons';

const page = usePage();
const { open, isMobile, isHovered } = useSidebar();

const entries = computed(() => (page.props.sidebar ?? []) as NavEntry[]);
const store = computed(() => page.props.store as { name: string } | null);
const path = computed(() => page.url.split('?')[0]);
const showText = computed(() => open.value || isHovered.value || isMobile.value);

function hrefOf(entry: NavEntry): string {
    return route(entry.route as string);
}

const links = computed(() => entries.value.flatMap((entry) => (entry.type === 'group' ? (entry.children ?? []) : [entry])).map((entry) => new URL(hrefOf(entry)).pathname));

/** The one link that best matches the page: on /products/add that is "Add product", not also "Products". */
const activeLink = computed(() => {
    const home = new URL(route('dashboard')).pathname;
    const matches = links.value.filter((link) => path.value === link || (link !== home && path.value.startsWith(`${link}/`)));

    return matches.sort((a, b) => b.length - a.length)[0] ?? null;
});

function isActive(entry: NavEntry): boolean {
    return new URL(hrefOf(entry)).pathname === activeLink.value;
}

const groupWithActiveChild = (): string | null => entries.value.find((entry) => (entry.children ?? []).some(isActive))?.title ?? null;

const openGroup = ref<string | null>(groupWithActiveChild());

watch(path, () => (openGroup.value = groupWithActiveChild()));

function toggleGroup(title: string): void {
    openGroup.value = openGroup.value === title ? null : title;
}
</script>

<template>
    <!-- `left` is the logical start edge: the sidebar follows the page direction. -->
    <Sidebar side="left" collapsible="icon">
        <SidebarHeader class="h-20 justify-center px-5 py-0">
            <Link :href="route('dashboard')" class="flex items-center gap-2.5" :class="{ 'justify-center': !showText }" :title="store?.name">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-white"><Store class="size-5.5" /></span>
                <span v-if="showText && store" class="truncate text-lg font-semibold text-gray-800 dark:text-white/90">{{ store.name }}</span>
            </Link>
        </SidebarHeader>

        <SidebarContent class="custom-scrollbar overflow-x-hidden overflow-y-auto px-5 duration-300 ease-linear">
            <nav class="mb-6" :aria-label="t('Main menu')">
                <SidebarGroup class="p-0">
                    <SidebarGroupLabel class="mb-3 flex h-5 items-center p-0 text-xs leading-5 text-gray-400" :class="{ 'justify-center': !showText }">
                        <template v-if="showText">{{ t('Menu') }}</template>
                        <MoreHorizontal v-else class="size-5" />
                    </SidebarGroupLabel>
                    <SidebarMenu class="gap-1">
                        <template v-for="entry in entries" :key="entry.title">
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
                    </SidebarMenu>
                </SidebarGroup>
            </nav>
        </SidebarContent>
    </Sidebar>
</template>
