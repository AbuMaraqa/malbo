<script setup lang="ts">
import { t } from '@/lib/i18n';
import { Check } from '@/lib/icons';

import type { TrackingStage } from './types';

/** The order's stages, top to bottom: reached ones with their date, the current one marked. */
defineProps<{ stages: TrackingStage[]; final: boolean }>();

/** A failed end (cancelled, rejected, returned) is red; an exchange is a plain, neutral end. */
const dotClass: Record<TrackingStage['tone'], string> = {
    done: 'border-store bg-store text-store-contrast',
    pending: 'border-line bg-(--shell)',
    danger: 'border-(--danger) bg-(--danger) text-white',
    info: 'border-(--ink-soft) bg-(--ink-soft) text-white',
};
</script>

<template>
    <ol class="relative ms-3 border-s-2 border-line">
        <li v-for="stage in stages" :key="stage.key" class="relative ps-8 pb-7 last:pb-0" :aria-current="stage.current ? 'step' : undefined">
            <span
                aria-hidden="true"
                class="absolute -start-[0.8125rem] top-0 grid size-6 place-items-center rounded-full border-2"
                :class="dotClass[stage.tone]"
            >
                <Check v-if="stage.reached" class="size-3.5" />
            </span>
            <p class="leading-6" :class="stage.reached ? 'font-bold' : 'text-muted'">
                {{ stage.label }}
                <span v-if="stage.current && !final" class="ms-2 rounded-full bg-store-50 px-2 py-0.5 text-xs text-store-700">{{ t('Now') }}</span>
            </p>
            <p v-if="stage.date" class="text-sm text-muted" dir="ltr">{{ stage.date }}</p>
        </li>
    </ol>
</template>
