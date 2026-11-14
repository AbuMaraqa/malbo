<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

export interface StatusHistoryRow {
    id: number;
    status: string;
    tone: 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand';
    at: string | null;
    by: string | null;
}

/** Every status the order reached, oldest first, with when and by whom (empty: the buyer). */
defineProps<{ history: StatusHistoryRow[] }>();
</script>

<template>
    <Card :title="t('Status history')">
        <p v-if="!history.length" class="text-sm text-muted">{{ t('No status changes recorded yet.') }}</p>
        <ol v-else class="grid gap-3">
            <li v-for="row in history" :key="row.id" class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <Badge :tone="row.tone">{{ row.status }}</Badge>
                <span class="text-muted">
                    <span dir="ltr">{{ row.at }}</span>
                    · {{ row.by ?? t('Customer') }}
                </span>
            </li>
        </ol>
    </Card>
</template>
