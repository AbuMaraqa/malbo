<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import InitialTile from './InitialTile.vue';
import SectionHeading from './SectionHeading.vue';
import { container } from './styles';
import type { StoreCategory } from './types';

/** The root categories as round pictures with their names, in one row that wraps. */
defineProps<{ categories: StoreCategory[] }>();
</script>

<template>
    <section data-section="categories" :class="container" class="reveal mt-20">
        <SectionHeading :title="t('Shop by category')" :lead="t('Find what you are looking for, faster.')" />
        <ul class="mt-10 flex flex-wrap justify-center gap-x-6 gap-y-8 sm:gap-x-10">
            <li v-for="category in categories" :key="category.id" class="w-28 sm:w-36">
                <Link :href="category.url" class="group block text-center">
                    <span class="block aspect-square overflow-hidden rounded-full border-4 border-white bg-(--sand) shadow-[0_8px_24px_-12px_rgb(22_28_45/0.35)] ring-1 ring-line transition group-hover:ring-2 group-hover:ring-store">
                        <img
                            v-if="category.image"
                            :src="category.image"
                            alt=""
                            loading="lazy"
                            class="size-full object-cover transition duration-500 group-hover:scale-110 motion-reduce:transition-none"
                        />
                        <InitialTile v-else :name="category.name" class="text-4xl" />
                    </span>
                    <span class="mt-4 block font-semibold transition-colors group-hover:text-store-600">{{ category.name }}</span>
                </Link>
            </li>
        </ul>
    </section>
</template>
