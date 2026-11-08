<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, ShoppingBag, Tag } from '@/lib/icons';

import { t } from '@/lib/i18n';

import type { QuickLink } from './types';
import WidgetCard from './WidgetCard.vue';

defineProps<{ links: QuickLink[] }>();

const icons = { branches: MapPin, products: ShoppingBag, categories: Tag };
</script>

<template>
    <WidgetCard :title="t('Quick Access')">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <template v-for="link in links" :key="link.key">
                <Link v-if="link.url" :href="link.url" class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 dark:border-gray-800 dark:text-gray-300 dark:hover:border-brand-500 dark:hover:bg-brand-500/10 dark:hover:text-brand-300">
                    <component :is="icons[link.key]" class="size-5 shrink-0" />
                    {{ link.label }}
                </Link>
                <span v-else aria-disabled="true" :title="t('Coming Soon')" class="flex cursor-not-allowed items-center gap-3 rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm font-medium text-gray-400 dark:border-gray-700 dark:text-gray-500">
                    <component :is="icons[link.key]" class="size-5 shrink-0" />
                    {{ link.label }}
                </span>
            </template>
        </div>
    </WidgetCard>
</template>
