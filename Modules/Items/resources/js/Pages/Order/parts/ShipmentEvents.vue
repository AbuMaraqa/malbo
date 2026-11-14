<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import { t } from '@/lib/i18n';

import type { ShipmentEventRow } from './shipment';

/** Every status the shipment reached, oldest first, with its note, when and by whom. */
defineProps<{ events: ShipmentEventRow[] }>();
</script>

<template>
    <ol class="grid gap-3">
        <li v-for="event in events" :key="event.id" class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <span class="flex flex-wrap items-center gap-2">
                <Badge :tone="event.tone">{{ event.status }}</Badge>
                <span v-if="event.note" class="text-ink">{{ event.note }}</span>
            </span>
            <span class="text-muted">
                <span dir="ltr">{{ event.at }}</span>
                · {{ event.by ?? t('System') }}
            </span>
        </li>
    </ol>
</template>
