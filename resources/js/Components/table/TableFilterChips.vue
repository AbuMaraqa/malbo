<script setup lang="ts">
import { X } from '@/lib/icons';

import { t } from '@/lib/i18n';
import type { FilterChip } from '@/types/table';

defineProps<{ chips: FilterChip[] }>();

const emit = defineEmits<{ remove: [keys: string[]]; reset: [] }>();
</script>

<template>
    <div v-if="chips.length" class="flex flex-wrap items-center gap-1.5 border-b border-line bg-surface/50 px-4 py-2 text-xs">
        <span class="text-muted">{{ t('Active filters') }}</span>
        <span v-for="chip in chips" :key="chip.keys.join()" class="inline-flex items-center gap-1 rounded-full bg-brand-50 dark:bg-brand-500/15 py-0.5 ps-2.5 pe-1 font-medium text-brand-600 dark:text-brand-400">
            {{ chip.label }}
            <button type="button" class="rounded-full p-0.5 hover:bg-brand-100 dark:hover:bg-brand-500/25" :aria-label="t('Remove filter')" @click="emit('remove', chip.keys)"><X class="size-3" /></button>
        </span>
        <button type="button" class="ms-1 text-muted underline hover:text-ink" @click="emit('reset')">{{ t('Clear all') }}</button>
    </div>
</template>
