<script setup lang="ts">
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, DialogTrigger } from 'reka-ui';
import { ref } from 'vue';

import { t } from '@/lib/i18n';
import { ListFilter, X } from '@/lib/icons';

import { btnBrand } from './styles';

/**
 * The phones' filters: its "Filters" button and a sheet from the end edge (Reka's Dialog: focus kept
 * inside and given back to the button, the page behind still, Escape and the backdrop close it).
 * Choices apply at once; the bottom button closes the sheet on the results.
 */
defineOptions({ inheritAttrs: false }); // classes go to the button: the dialog itself is teleported
defineProps<{ totalLabel: string; count: number }>();

const open = ref(false);
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogTrigger class="flex h-10 cursor-pointer items-center gap-2 rounded-md border border-line bg-white px-3 text-sm font-semibold" v-bind="$attrs">
            <ListFilter class="size-4.5" aria-hidden="true" />
            {{ t('Filters') }}
            <span v-if="count" class="grid h-5 min-w-5 place-items-center rounded-full bg-store px-1 text-[0.6875rem] leading-none text-store-contrast">{{ count }}</span>
        </DialogTrigger>
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-50 bg-(--night)/50" />
            <DialogContent class="fixed inset-y-0 end-0 z-50 flex w-[min(24rem,90vw)] flex-col bg-white shadow-2xl outline-none" :aria-describedby="undefined">
                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <DialogTitle class="text-lg font-bold">{{ t('Filters') }}</DialogTitle>
                    <DialogClose class="grid size-11 cursor-pointer place-items-center rounded-md hover:bg-(--sand)" :aria-label="t('Close filters')">
                        <X class="size-5" aria-hidden="true" />
                    </DialogClose>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-5">
                    <slot />
                </div>
                <div class="border-t border-line p-4">
                    <DialogClose :class="btnBrand" class="w-full">{{ t('Show :label', { label: totalLabel }) }}</DialogClose>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
