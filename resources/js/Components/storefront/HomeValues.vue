<script setup lang="ts">
import { computed } from 'vue';

import { t } from '@/lib/i18n';

import SectionHeading from './SectionHeading.vue';
import { container } from './styles';
import type { BrandValueText } from './types';

const props = defineProps<{ values: BrandValueText[] }>();

const grid = computed(() => ['', 'md:grid-cols-1', 'md:grid-cols-2', 'md:grid-cols-3'][props.values.length] ?? 'md:grid-cols-3');
const number = (index: number): string => String(index + 1).padStart(2, '0');
</script>

<template>
    <section data-section="values" class="reveal mt-24 bg-(--sand) bg-brand-pattern-soft py-20">
        <div :class="container">
            <SectionHeading :title="t('What we believe in')" :lead="t('Our promise')" />
            <ul class="mt-12 grid gap-5" :class="grid">
                <li
                    v-for="(value, index) in values"
                    :key="index"
                    class="rounded-lg border border-line bg-white p-8 transition duration-300 hover:-translate-y-1 hover:shadow-[0_18px_40px_-24px_rgb(42_21_26/0.4)] motion-reduce:transition-none"
                >
                    <span class="grid size-12 place-items-center rounded-full bg-store text-lg font-bold text-(--gold) tabular-nums">{{ number(index) }}</span>
                    <h3 class="mt-6 text-xl font-bold">{{ value.title }}</h3>
                    <p v-if="value.text" class="mt-2 text-[15px] leading-7 text-(--ink-soft)">{{ value.text }}</p>
                </li>
            </ul>
        </div>
    </section>
</template>
