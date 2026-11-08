<script setup lang="ts">
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { X } from '@/lib/icons';

import { t } from '@/lib/i18n';

withDefaults(defineProps<{ title: string; description?: string; width?: string }>(), { width: 'max-w-lg' });

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-[80] bg-gray-900/50 backdrop-blur-[2px]" />
            <DialogContent
                class="fixed start-1/2 top-[8vh] z-[90] max-h-[84vh] w-[calc(100vw-2rem)] overflow-y-auto rounded-2xl border border-line bg-card shadow-xl ltr:-translate-x-1/2 rtl:translate-x-1/2"
                :class="width"
                :aria-describedby="description ? undefined : undefined"
            >
                <header class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                    <div>
                        <DialogTitle class="text-base font-semibold">{{ title }}</DialogTitle>
                        <DialogDescription v-if="description" class="mt-1 text-sm text-muted">{{ description }}</DialogDescription>
                    </div>
                    <DialogClose class="rounded p-1 text-muted hover:bg-surface hover:text-ink" :aria-label="t('Close')">
                        <X class="size-4" />
                    </DialogClose>
                </header>
                <div class="px-5 py-4"><slot /></div>
                <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-line px-5 py-3">
                    <slot name="footer" />
                </footer>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
