<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import { t } from '@/lib/i18n';

/**
 * Time left until the offer ends. The server sends the seconds left, so the first paint (SSR) and
 * the browser agree; the clock ticks only once mounted.
 */
const props = defineProps<{ block: { heading: string | null; text: string | null; seconds_left: number } }>();

const left = ref(props.block.seconds_left);
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    timer = setInterval(() => {
        left.value = Math.max(0, left.value - 1);

        if (left.value === 0) clearInterval(timer);
    }, 1000);
});

onBeforeUnmount(() => clearInterval(timer));

const parts = computed(() => [
    { key: 'days', value: Math.floor(left.value / 86400), label: t('Days') },
    { key: 'hours', value: Math.floor((left.value % 86400) / 3600), label: t('Hours') },
    { key: 'minutes', value: Math.floor((left.value % 3600) / 60), label: t('Minutes') },
    { key: 'seconds', value: left.value % 60, label: t('Seconds') },
]);
</script>

<template>
    <section class="rounded-3xl bg-(--night) bg-malbo-pattern px-6 py-8 text-center text-white">
        <h2 v-if="block.heading" class="text-xl font-bold">{{ block.heading }}</h2>
        <p v-if="block.text" class="mt-1 text-white/80">{{ block.text }}</p>
        <p v-if="left === 0" class="mt-4 text-lg font-semibold">{{ t('This offer has ended') }}</p>
        <ol v-else class="mt-5 flex justify-center gap-3" dir="ltr" role="timer" aria-live="off">
            <li v-for="part in parts" :key="part.key" class="min-w-16 rounded-md bg-white/10 px-3 py-2">
                <span class="block text-2xl font-bold tabular-nums">{{ String(part.value).padStart(2, '0') }}</span>
                <span class="text-xs text-white/70">{{ part.label }}</span>
            </li>
        </ol>
    </section>
</template>
