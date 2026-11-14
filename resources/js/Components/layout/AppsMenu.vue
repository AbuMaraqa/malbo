<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { DropdownMenuItem } from 'reka-ui';
import { computed } from 'vue';

import DropdownMenu from '@/Components/ui/DropdownMenu.vue';
import { t } from '@/lib/i18n';
import { Widgets } from '@/lib/icons';

import { fallbackNavIcon, navIcons } from './navIcons';
import { useNavigation } from './useNavigation';

defineProps<{ buttonClass?: string }>();

const { entries, hrefOf } = useNavigation();

/** Every section of the panel as a tile; a group opens on its first page. */
const tiles = computed(() =>
    entries.value
        .map((entry) => {
            const target = entry.type === 'group' ? entry.children?.[0] : entry;

            return target ? { title: t(entry.title), icon: navIcons[entry.icon] ?? fallbackNavIcon, href: hrefOf(target) } : null;
        })
        .filter((tile) => tile !== null),
);
</script>

<template>
    <DropdownMenu content-class="w-[22rem] p-0">
        <template #trigger>
            <button type="button" :class="buttonClass" :aria-label="t('Sections')" :title="t('Sections')"><Widgets /></button>
        </template>

        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Sections') }}</p>
            <span class="tabular text-xs text-muted">{{ tiles.length }}</span>
        </div>
        <div class="grid grid-cols-3 gap-1 p-2">
            <DropdownMenuItem v-for="tile in tiles" :key="tile.href" as-child>
                <Link
                    :href="tile.href"
                    class="group flex flex-col items-center gap-2 rounded-lg px-2 py-3 text-center outline-none data-highlighted:bg-gray-100 dark:data-highlighted:bg-white/5"
                >
                    <span class="flex size-10 items-center justify-center rounded-lg border border-line bg-card text-gray-500 transition-colors group-data-highlighted:border-brand-200 group-data-highlighted:text-brand-500 dark:text-gray-400 dark:group-data-highlighted:border-brand-500/30">
                        <component :is="tile.icon" class="size-5" />
                    </span>
                    <span class="line-clamp-2 text-xs font-medium text-gray-700 dark:text-gray-300">{{ tile.title }}</span>
                </Link>
            </DropdownMenuItem>
        </div>
    </DropdownMenu>
</template>
