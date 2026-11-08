<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { ChevronDown } from '@/lib/icons';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/tailadmin/ui/collapsible';
import { SidebarMenuItem } from '@/tailadmin/ui/sidebar';

import { fallbackNavIcon, navIcons, type NavEntry } from './navIcons';

const props = defineProps<{
    entry: NavEntry;
    open: boolean;
    showText: boolean;
    hrefOf: (entry: NavEntry) => string;
    isActive: (entry: NavEntry) => boolean;
}>();

const emit = defineEmits<{ toggle: [] }>();

const hasActiveChild = computed(() => (props.entry.children ?? []).some(props.isActive));
const highlighted = computed(() => props.open || hasActiveChild.value);
</script>

<template>
    <SidebarMenuItem>
        <Collapsible :open="showText && open" @update:open="emit('toggle')">
            <CollapsibleTrigger
                class="group menu-item cursor-pointer"
                :class="[highlighted ? 'menu-item-active' : 'menu-item-inactive', { 'justify-center': !showText }]"
                :title="showText ? undefined : t(entry.title)"
                :aria-label="t(entry.title)"
            >
                <span class="shrink-0" :class="highlighted ? 'menu-item-icon-active' : 'menu-item-icon-inactive'">
                    <component :is="navIcons[entry.icon] ?? fallbackNavIcon" class="block size-6" />
                </span>
                <span v-if="showText" class="menu-item-text truncate">{{ t(entry.title) }}</span>
                <ChevronDown v-if="showText" class="ms-auto size-5 shrink-0 transition-transform duration-200" :class="{ 'rotate-180 text-brand-500': open }" />
            </CollapsibleTrigger>
            <CollapsibleContent>
                <ul class="ms-9 mt-2 space-y-1">
                    <li v-for="child in entry.children" :key="child.title">
                        <Link
                            :href="hrefOf(child)"
                            class="group menu-dropdown-item"
                            :class="isActive(child) ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'"
                            :aria-current="isActive(child) ? 'page' : undefined"
                        >
                            {{ t(child.title) }}
                        </Link>
                    </li>
                </ul>
            </CollapsibleContent>
        </Collapsible>
    </SidebarMenuItem>
</template>
