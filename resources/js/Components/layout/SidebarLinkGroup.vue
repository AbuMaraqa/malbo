<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CollapsibleContent, CollapsibleRoot, CollapsibleTrigger } from 'reka-ui';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { MinusSmall, PlusSmall } from '@/lib/icons';

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
</script>

<template>
    <li>
        <CollapsibleRoot :open="showText && open" @update:open="emit('toggle')">
            <CollapsibleTrigger
                class="group flex h-10 w-full items-center gap-3 rounded-lg px-2.5 text-sm font-medium transition-colors"
                :class="[
                    hasActiveChild ? 'text-gray-900 dark:text-white' : 'text-gray-700 hover:bg-gray-50 hover:text-brand-600 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-brand-400',
                    { 'justify-center': !showText, 'bg-gray-100 dark:bg-white/5': hasActiveChild && !showText },
                ]"
                :title="showText ? undefined : t(entry.title)"
                :aria-label="showText ? undefined : t(entry.title)"
            >
                <component
                    :is="navIcons[entry.icon] ?? fallbackNavIcon"
                    class="size-5 shrink-0 transition-colors"
                    :class="hasActiveChild ? 'text-brand-500 dark:text-brand-400' : 'text-gray-400 group-hover:text-brand-500 dark:text-gray-500 dark:group-hover:text-brand-400'"
                />
                <template v-if="showText">
                    <span class="flex-1 truncate text-start">{{ t(entry.title) }}</span>
                    <MinusSmall v-if="open" class="size-3.5 shrink-0 text-gray-400" />
                    <PlusSmall v-else class="size-3.5 shrink-0 text-gray-400" />
                </template>
            </CollapsibleTrigger>

            <CollapsibleContent class="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down">
                <!-- The line on the start edge ties the children to their parent, under its icon. -->
                <ul class="relative ms-[19px] mt-0.5 space-y-0.5 border-s border-line ps-3">
                    <li v-for="child in entry.children" :key="child.title">
                        <Link
                            :href="hrefOf(child)"
                            class="group flex h-9 items-center gap-2.5 rounded-lg px-2.5 text-[13px] font-medium transition-colors"
                            :class="isActive(child) ? 'bg-gray-100 text-brand-600 dark:bg-white/5 dark:text-brand-400' : 'text-gray-600 hover:bg-gray-50 hover:text-brand-600 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-brand-400'"
                            :aria-current="isActive(child) ? 'page' : undefined"
                        >
                            <span class="size-1.5 shrink-0 rounded-full transition-colors" :class="isActive(child) ? 'bg-brand-500' : 'bg-gray-300 group-hover:bg-brand-400 dark:bg-gray-600'" aria-hidden="true" />
                            <span class="truncate">{{ t(child.title) }}</span>
                        </Link>
                    </li>
                </ul>
            </CollapsibleContent>
        </CollapsibleRoot>
    </li>
</template>
