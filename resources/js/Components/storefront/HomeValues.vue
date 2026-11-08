<script setup lang="ts">
import { computed } from 'vue';

import { t } from '@/lib/i18n';

import { eyebrow, sectionTitle } from './styles';
import type { BrandValueText } from './types';

const props = defineProps<{ values: BrandValueText[] }>();

const grid = computed(() => ['', 'md:grid-cols-1', 'md:grid-cols-2', 'md:grid-cols-3'][props.values.length] ?? 'md:grid-cols-3');
const number = (index: number): string => String(index + 1).padStart(2, '0');
</script>

<template>
    <section data-section="values" class="reveal mx-auto mt-24 max-w-310 px-4">
        <div class="text-center">
            <p :class="eyebrow">{{ t('Our promise') }}</p>
            <h2 class="mt-2" :class="sectionTitle">{{ t('What we believe in') }}</h2>
        </div>
        <ul class="mt-12 grid gap-10 md:gap-0" :class="grid">
            <li v-for="(value, index) in values" :key="index" class="md:border-s md:border-line md:px-10 md:first:border-s-0">
                <span class="block text-5xl font-light text-store-700 tabular-nums">{{ number(index) }}</span>
                <h3 class="mt-5 text-xl font-bold">{{ value.title }}</h3>
                <p v-if="value.text" class="mt-2 text-[15px] leading-7 text-(--ink-soft)">{{ value.text }}</p>
            </li>
        </ul>
    </section>
</template>
