<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import InitialTile from './InitialTile.vue';
import PriceRow from './PriceRow.vue';
import { glass } from './styles';
import type { StoreCard } from './types';

defineProps<{ card: StoreCard }>();
</script>

<template>
    <article class="group relative flex flex-col gap-3.5">
        <div class="relative aspect-4/5 overflow-hidden rounded-2xl bg-(--sand-deep)">
            <img
                v-if="card.image"
                :src="card.image"
                alt=""
                loading="lazy"
                decoding="async"
                class="size-full object-cover transition duration-700 group-hover:scale-[1.04] motion-reduce:transition-none"
            />
            <InitialTile v-else :name="card.name" class="text-4xl" />
            <span v-if="card.price.discounted" class="absolute start-3 top-3 rounded-full px-2.5 py-1 text-xs font-bold" :class="glass">
                {{ t('Sale') }} <span dir="ltr">−{{ card.price.percent_off }}%</span>
            </span>
            <span
                aria-hidden="true"
                class="absolute inset-x-3 bottom-3 hidden translate-y-2 rounded-xl py-2.5 text-center text-sm font-bold opacity-0 transition duration-300 group-hover:translate-y-0 group-hover:opacity-100 motion-reduce:transition-none lg:block"
                :class="glass"
            >
                {{ t('View product') }}
            </span>
        </div>
        <div class="flex flex-col gap-1">
            <span v-if="card.category" class="text-[13px] text-muted">{{ card.category }}</span>
            <h3 class="text-[17px] leading-snug font-bold">
                <Link :href="card.url" class="after:absolute after:inset-0">{{ card.name }}</Link>
            </h3>
            <PriceRow :price="card.price" />
        </div>
    </article>
</template>
