<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import SectionHeading from './SectionHeading.vue';
import type { InstagramPostCard } from './types';

/**
 * Instagram posts as a strip of square pictures from edge to edge, under a centered title. Each
 * picture opens the post on Instagram; the products it shows ("shop the look") appear over it on
 * hover and keyboard focus, linking to their pages. On the home page and as a campaign block.
 */
defineProps<{ title: string; profileUrl?: string | null; posts: InstagramPostCard[] }>();
</script>

<template>
    <div>
        <div class="px-4">
            <SectionHeading :title="title" />
            <p v-if="profileUrl" class="mt-4 text-center">
                <a :href="profileUrl" target="_blank" rel="noopener" class="text-[0.8125rem] font-semibold ltr:tracking-[0.1em] text-(--gold-ink) ltr:uppercase hover:text-store-600">{{ t('Follow us') }}</a>
            </p>
        </div>
        <ul class="mt-10 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
            <li v-for="post in posts.slice(0, 10)" :key="post.id" class="group relative aspect-square overflow-hidden bg-(--sand)">
                <a :href="post.url" target="_blank" rel="noopener" class="block size-full" :aria-label="post.caption ?? t('Open on Instagram')">
                    <img v-if="post.image" :src="post.image" :alt="post.caption ?? ''" class="size-full object-cover transition duration-500 group-hover:scale-105 motion-reduce:transition-none" loading="lazy" />
                </a>
                <div
                    v-if="post.products.length"
                    class="absolute inset-x-0 bottom-0 flex flex-wrap gap-1.5 bg-linear-to-t from-(--night)/80 to-transparent p-3 pt-10 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100"
                >
                    <span class="sr-only">{{ t('Shop the look') }}</span>
                    <Link v-for="product in post.products" :key="product.url" :href="product.url" class="bg-(--shell) px-2.5 py-1 text-xs font-semibold text-ink hover:bg-(--gold)">
                        {{ product.name }}
                    </Link>
                </div>
            </li>
        </ul>
    </div>
</template>
