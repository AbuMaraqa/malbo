<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import { linkArrow, sectionTitle } from './styles';
import type { InstagramPostCard } from './types';

/**
 * Instagram posts: each picture opens the post on Instagram; the products it shows ("shop the
 * look") link to their pages. On the home page and as a campaign block.
 */
defineProps<{ title: string; profileUrl?: string | null; posts: InstagramPostCard[] }>();
</script>

<template>
    <div>
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-line pb-4">
            <h2 :class="sectionTitle">{{ title }}</h2>
            <a v-if="profileUrl" :href="profileUrl" target="_blank" rel="noopener" :class="linkArrow">{{ t('Follow us') }}</a>
        </div>
        <ul class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-4">
            <li v-for="post in posts" :key="post.id" class="flex flex-col">
                <a :href="post.url" target="_blank" rel="noopener" class="block aspect-square overflow-hidden rounded-md bg-(--sand)" :aria-label="post.caption ?? t('Open on Instagram')">
                    <img v-if="post.image" :src="post.image" :alt="post.caption ?? ''" class="size-full object-cover transition-transform hover:scale-105" loading="lazy" />
                </a>
                <p v-if="post.caption" class="mt-2 line-clamp-2 text-sm text-(--ink-soft)">{{ post.caption }}</p>
                <div v-if="post.products.length" class="mt-2 flex flex-wrap gap-1.5">
                    <span class="sr-only">{{ t('Shop the look') }}</span>
                    <Link v-for="product in post.products" :key="product.url" :href="product.url" class="rounded-full border border-line px-2.5 py-1 text-xs font-medium hover:border-store hover:text-store-700">
                        {{ product.name }}
                    </Link>
                </div>
            </li>
        </ul>
    </div>
</template>
