<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';

import ArrowMark from './ArrowMark.vue';
import InitialTile from './InitialTile.vue';
import { eyebrow, glass, sectionTitle } from './styles';
import type { StoreCategory } from './types';

const props = defineProps<{ categories: StoreCategory[] }>();

// Few categories fill the row with wide cards instead of leaving it half empty.
const grid = computed(() => {
    const count = props.categories.length;

    if (count <= 1) return 'grid-cols-1';
    if (count === 2) return 'grid-cols-2';
    if (count === 3) return 'grid-cols-2 sm:grid-cols-3';

    return 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4';
});
const shape = computed(() => (props.categories.length <= 2 ? 'aspect-16/10' : 'aspect-4/5'));
</script>

<template>
    <section data-section="categories" class="reveal mx-auto mt-24 max-w-310 px-4">
        <p :class="eyebrow">{{ t('Collections') }}</p>
        <h2 class="mt-2" :class="sectionTitle">{{ t('Shop by category') }}</h2>
        <ul class="mt-8 grid gap-4 sm:gap-5" :class="grid">
            <li v-for="category in categories" :key="category.id">
                <Link :href="category.url" class="group relative block overflow-hidden rounded-2xl bg-store-50" :class="shape">
                    <img
                        v-if="category.image"
                        :src="category.image"
                        alt=""
                        loading="lazy"
                        class="size-full object-cover transition duration-700 group-hover:scale-[1.05] motion-reduce:transition-none"
                    />
                    <InitialTile v-else :name="category.name" class="text-6xl" />
                    <span class="absolute inset-x-3 bottom-3 flex items-center justify-between gap-3 rounded-xl px-4 py-3" :class="glass">
                        <span class="truncate font-bold">{{ category.name }}</span>
                        <ArrowMark class="text-store-700 transition group-hover:ltr:translate-x-1 group-hover:rtl:-translate-x-1 motion-reduce:transition-none" />
                    </span>
                </Link>
            </li>
        </ul>
    </section>
</template>
