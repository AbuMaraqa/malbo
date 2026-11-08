<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, X } from '@/lib/icons';
import { ref, watch } from 'vue';

import { t } from '@/lib/i18n';

interface Toast {
    id: number;
    kind: 'success' | 'error';
    text: string;
}

const page = usePage();
const toasts = ref<Toast[]>([]);
let next = 0;

function dismiss(id: number): void {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function push(kind: Toast['kind'], text: string): void {
    const id = ++next;
    toasts.value.push({ id, kind, text });

    if (kind === 'success') {
        setTimeout(() => dismiss(id), 4000);
    }
}

watch(
    () => page.props.flash as { success?: string | null; error?: string | null } | undefined,
    (flash) => {
        if (flash?.success) push('success', flash.success);
        if (flash?.error) push('error', flash.error);
    },
    { immediate: true },
);
</script>

<template>
    <div class="fixed bottom-4 end-4 z-[110] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2" aria-live="polite">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="flex items-start gap-2 rounded-control border bg-card p-3 text-sm shadow-md"
            :class="toast.kind === 'success' ? 'border-success/30' : 'border-danger/40'"
        >
            <CircleCheck v-if="toast.kind === 'success'" class="mt-0.5 size-4 shrink-0 text-success" />
            <CircleAlert v-else class="mt-0.5 size-4 shrink-0 text-danger" />
            <p class="flex-1">{{ toast.text }}</p>
            <button class="text-muted hover:text-ink" :aria-label="t('Close')" @click="dismiss(toast.id)"><X class="size-4" /></button>
        </div>
    </div>
</template>
