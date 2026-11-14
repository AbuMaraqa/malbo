<script setup lang="ts">
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';

import { t } from '@/lib/i18n';
import { X } from '@/lib/icons';

/** A panel that slides in from the end edge, floating a little off it: notifications, details. */
withDefaults(defineProps<{ title: string; description?: string; width?: string }>(), { width: 'sm:w-[420px]' });

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-[80] bg-gray-950/30 backdrop-blur-[3px] data-[state=open]:animate-fade-in" />
            <DialogContent
                class="fixed inset-y-0 end-0 z-[90] flex w-full flex-col overflow-hidden border-line bg-card shadow-theme-xl outline-none [--slide-from:1] rtl:[--slide-from:-1] data-[state=open]:animate-slide-in-end sm:inset-y-3 sm:end-3 sm:rounded-xl sm:border"
                :class="width"
            >
                <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div class="min-w-0">
                        <DialogTitle class="text-base font-semibold text-gray-900 dark:text-white">{{ title }}</DialogTitle>
                        <DialogDescription v-if="description" class="mt-0.5 text-sm text-muted">{{ description }}</DialogDescription>
                    </div>
                    <DialogClose class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white" :aria-label="t('Close')">
                        <X class="size-5" />
                    </DialogClose>
                </header>
                <slot name="toolbar" />
                <div class="custom-scrollbar min-h-0 flex-1 overflow-y-auto"><slot /></div>
                <footer v-if="$slots.footer" class="grid grid-cols-2 gap-2 border-t border-line p-4"><slot name="footer" /></footer>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
