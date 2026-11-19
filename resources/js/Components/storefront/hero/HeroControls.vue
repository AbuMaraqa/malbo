<script setup lang="ts">
import { t } from '@/lib/i18n';
import { ChevronLeft, ChevronRight } from '@/lib/icons';

/**
 * The slider's controls: a bar per slide (the current one fills over the slide's time and stops
 * with the slider), arrows on wide screens and the slide number.
 */
defineProps<{
    count: number;
    current: number;
    /** How long a slide stays, in milliseconds: the time the current bar takes to fill. */
    interval: number;
    playing: boolean;
    paused: boolean;
}>();

defineEmits<{ go: [index: number]; step: [by: 1 | -1] }>();

const pad = (value: number): string => String(value).padStart(2, '0');

const arrow =
    'absolute top-1/2 z-20 hidden size-12 -translate-y-1/2 cursor-pointer place-items-center rounded-full border border-white/25 bg-white/10 text-white backdrop-blur-sm transition-[background-color,color,transform] duration-300 hover:scale-105 hover:border-(--gold) hover:bg-(--gold) hover:text-(--night) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--gold) lg:grid';
</script>

<template>
    <div class="absolute inset-x-0 bottom-6 z-20 mx-auto flex w-full max-w-310 items-center justify-center gap-5 px-5 sm:bottom-8 sm:justify-between sm:px-6">
        <div class="flex items-center gap-2">
            <button
                v-for="index in count"
                :key="index"
                type="button"
                class="group relative h-6 cursor-pointer transition-[width] duration-500"
                :class="index - 1 === current ? 'w-12' : 'w-6'"
                :aria-label="t('Go to slide :number', { number: index })"
                :aria-current="index - 1 === current"
                @click="$emit('go', index - 1)"
            >
                <span class="absolute inset-x-0 top-1/2 h-[3px] -translate-y-1/2 overflow-hidden rounded-full bg-white/35 transition-colors group-hover:bg-white/60">
                    <span
                        v-if="index - 1 === current"
                        :key="current"
                        class="hero-progress absolute inset-y-0 start-0 block rounded-full bg-(--gold)"
                        :class="playing ? '' : 'w-full'"
                        :style="playing ? { animationDuration: `${interval}ms`, animationPlayState: paused ? 'paused' : 'running' } : undefined"
                    ></span>
                </span>
            </button>
        </div>
        <p class="hidden text-sm font-medium text-white/80 tabular-nums sm:block" dir="ltr" aria-hidden="true">
            <span class="text-base font-semibold text-white">{{ pad(current + 1) }}</span>
            <span class="mx-2 inline-block h-px w-8 bg-white/40 align-middle"></span>
            {{ pad(count) }}
        </p>
    </div>

    <button type="button" class="start-5" :class="arrow" :aria-label="t('Previous slide')" @click="$emit('step', -1)">
        <ChevronRight class="size-5 ltr:rotate-180" aria-hidden="true" />
    </button>
    <button type="button" class="end-5" :class="arrow" :aria-label="t('Next slide')" @click="$emit('step', 1)">
        <ChevronLeft class="size-5 ltr:rotate-180" aria-hidden="true" />
    </button>
</template>
