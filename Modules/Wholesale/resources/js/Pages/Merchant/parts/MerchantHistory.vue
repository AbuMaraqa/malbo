<script setup lang="ts">
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

import type { HistoryRow } from './types';

/** Every status change, newest first: who, when, why. */
defineProps<{ history: HistoryRow[] }>();
</script>

<template>
    <Card :title="t('History')">
        <ol class="grid gap-4">
            <li v-for="row in history" :key="row.id" class="border-s-2 border-line ps-3">
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                    {{ row.to }}
                    <span v-if="row.from" class="font-normal text-muted">({{ t('from :status', { status: row.from }) }})</span>
                </p>
                <p class="text-xs text-muted">{{ row.at }}<template v-if="row.by"> · {{ row.by }}</template></p>
                <p v-if="row.note" class="mt-1 text-sm whitespace-pre-line text-gray-600 dark:text-gray-400">{{ row.note }}</p>
            </li>
        </ol>
    </Card>
</template>
