<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { ArrowRightUp, MapPin, ShoppingBag, Tag } from '@/lib/icons';

import type { QuickLink } from './types';
import WidgetCard from './WidgetCard.vue';

defineProps<{ links: QuickLink[] }>();

const icons = { branches: MapPin, products: ShoppingBag, categories: Tag };
</script>

<template>
    <WidgetCard :title="t('Quick Access')">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <template v-for="link in links" :key="link.key">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="group flex items-center gap-3 rounded-xl border border-line p-3 text-sm font-medium text-gray-800 transition-colors hover:border-brand-200 hover:bg-brand-50/50 dark:text-gray-200 dark:hover:border-brand-500/30 dark:hover:bg-brand-500/5"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500 transition-colors group-hover:bg-brand-500 group-hover:text-white dark:bg-white/5 dark:text-gray-400">
                        <component :is="icons[link.key]" class="size-5" />
                    </span>
                    <span class="flex-1">{{ link.label }}</span>
                    <ArrowRightUp class="size-4 text-gray-400 transition-colors group-hover:text-brand-500 rtl:-scale-x-100" />
                </Link>
                <span v-else aria-disabled="true" :title="t('Coming Soon')" class="flex cursor-not-allowed items-center gap-3 rounded-xl border border-dashed border-line p-3 text-sm font-medium text-gray-400 dark:text-gray-500">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 dark:bg-white/[0.03]"><component :is="icons[link.key]" class="size-5" /></span>
                    <span class="flex-1">{{ link.label }}</span>
                    <span class="rounded-md border border-line px-1.5 py-0.5 text-[11px]">{{ t('Coming Soon') }}</span>
                </span>
            </template>
        </div>
    </WidgetCard>
</template>
