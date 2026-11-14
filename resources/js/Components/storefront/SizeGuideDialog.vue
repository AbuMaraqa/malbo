<script setup lang="ts">
import { ref } from 'vue';

import { t } from '@/lib/i18n';
import { Ruler, X } from '@/lib/icons';

import { prose } from './styles';
import type { SizeGuide } from './types';

/** "Size guide": a link-like button by the size choice that opens the category's guide. */
defineProps<{ guide: SizeGuide }>();

const dialog = ref<HTMLDialogElement>();

function open(): void {
    dialog.value?.showModal();
}

function close(): void {
    dialog.value?.close();
}

/** A click on the backdrop (the dialog itself, outside its panel) closes it. */
function onClick(event: MouseEvent): void {
    if (event.target === dialog.value) close();
}
</script>

<template>
    <button type="button" class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 text-sm font-bold text-store-700 underline-offset-4 hover:underline" aria-haspopup="dialog" @click="open">
        <Ruler class="size-4.5" aria-hidden="true" />
        {{ t('Size guide') }}
    </button>

    <dialog
        ref="dialog"
        class="m-auto max-h-[85vh] w-[min(42rem,calc(100%-2rem))] overflow-hidden rounded-lg bg-white p-0 text-ink shadow-xl backdrop:bg-black/50"
        aria-labelledby="size-guide-title"
        @click="onClick"
    >
        <div class="flex max-h-[85vh] flex-col">
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                <h2 id="size-guide-title" class="text-lg font-bold">{{ t('Size guide') }} — {{ guide.title }}</h2>
                <button type="button" class="grid size-10 shrink-0 cursor-pointer place-items-center rounded-full hover:bg-(--sand-light)" :aria-label="t('Close')" @click="close">
                    <X class="size-5" aria-hidden="true" />
                </button>
            </div>
            <!-- Sanitized on the server (RichText). Tables scroll sideways on a narrow screen. -->
            <div
                class="overflow-auto px-5 py-4 [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-line [&_td]:px-3 [&_td]:py-2 [&_th]:border [&_th]:border-line [&_th]:bg-(--sand-light) [&_th]:px-3 [&_th]:py-2 [&_th]:text-start"
                :class="prose"
                v-html="guide.html"
            ></div>
        </div>
    </dialog>
</template>
