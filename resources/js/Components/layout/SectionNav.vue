<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';

import { useNavigation } from './useNavigation';

const { entries, hrefOf, isActive } = useNavigation();

/** The sidebar group holding this page: its pages become tabs under the header. */
const group = computed(() => entries.value.find((entry) => entry.type === 'group' && (entry.children ?? []).some(isActive)) ?? null);
</script>

<template>
    <nav v-if="group && (group.children ?? []).length > 1" class="border-b border-line bg-canvas" :aria-label="t(group.title)">
        <ul class="no-scrollbar mx-auto flex w-full max-w-[1440px] gap-6 overflow-x-auto px-4 md:px-6 lg:px-8">
            <li v-for="child in group.children" :key="child.title" class="shrink-0">
                <Link
                    :href="hrefOf(child)"
                    class="-mb-px flex h-12 items-center border-b-2 text-sm font-medium whitespace-nowrap transition-colors"
                    :class="isActive(child) ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400'"
                    :aria-current="isActive(child) ? 'page' : undefined"
                >
                    {{ t(child.title) }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
