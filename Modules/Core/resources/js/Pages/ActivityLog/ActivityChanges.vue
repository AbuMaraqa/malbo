<script setup lang="ts">
import { computed, ref } from 'vue';

import { t } from '@/lib/i18n';

export interface ActivityChange {
    key: string;
    old: string | null;
    new: string | null;
}

/** The attributes an entry changed, old → new; long lists fold after a few. */
const props = defineProps<{ changes: ActivityChange[] }>();

const FOLDED = 4;
const open = ref(false);
const shown = computed(() => (open.value ? props.changes : props.changes.slice(0, FOLDED)));
</script>

<template>
    <span v-if="!changes.length" class="text-muted">—</span>
    <ul v-else class="grid max-w-xl gap-1 text-xs">
        <li v-for="change in shown" :key="change.key" class="break-words">
            <span class="font-medium text-ink" dir="ltr">{{ change.key }}</span>:
            <template v-if="change.old !== null">
                <span class="text-error-600 line-through decoration-1 dark:text-error-400">{{ change.old }}</span>
                <span class="mx-1 text-muted rtl:inline-block rtl:rotate-180">→</span>
            </template>
            <span class="text-success-700 dark:text-success-400">{{ change.new ?? '—' }}</span>
        </li>
        <li v-if="changes.length > FOLDED">
            <button type="button" class="text-brand-600 hover:underline dark:text-brand-400" @click="open = !open">
                {{ open ? t('Show less') : t('Show :count more', { count: changes.length - FOLDED }) }}
            </button>
        </li>
    </ul>
</template>
