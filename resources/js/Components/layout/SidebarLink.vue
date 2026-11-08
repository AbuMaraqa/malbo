<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { SidebarMenuItem } from '@/tailadmin/ui/sidebar';

import { fallbackNavIcon, navIcons, type NavEntry } from './navIcons';

defineProps<{ entry: NavEntry; href: string; active: boolean; showText: boolean }>();
</script>

<template>
    <SidebarMenuItem>
        <Link
            :href="href"
            class="group menu-item"
            :class="[active ? 'menu-item-active' : 'menu-item-inactive', { 'justify-center': !showText }]"
            :title="showText ? undefined : t(entry.title)"
            :aria-label="t(entry.title)"
            :aria-current="active ? 'page' : undefined"
        >
            <span class="shrink-0" :class="active ? 'menu-item-icon-active' : 'menu-item-icon-inactive'">
                <component :is="navIcons[entry.icon] ?? fallbackNavIcon" class="block size-6" />
            </span>
            <span v-if="showText" class="menu-item-text truncate">{{ t(entry.title) }}</span>
        </Link>
    </SidebarMenuItem>
</template>
