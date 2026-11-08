<script setup lang="ts">
import { SearchX } from '@/lib/icons';

import { t } from '@/lib/i18n';

defineProps<{ title?: string; text?: string; filtered?: boolean }>();

const emit = defineEmits<{ reset: [] }>();
</script>

<template>
    <div class="px-4 py-14 text-center">
        <span class="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-surface text-muted"><SearchX class="size-5" /></span>
        <template v-if="filtered">
            <p class="font-medium">{{ t('No results match these filters') }}</p>
            <button type="button" class="mt-2 text-sm text-brand-600 dark:text-brand-400 underline" @click="emit('reset')">{{ t('Clear all') }}</button>
        </template>
        <template v-else>
            <p class="font-medium">{{ title ?? t('Nothing here yet') }}</p>
            <p v-if="text" class="mt-1 text-sm text-muted">{{ text }}</p>
            <div v-if="$slots.default" class="mt-4 flex justify-center"><slot /></div>
        </template>
    </div>
</template>
