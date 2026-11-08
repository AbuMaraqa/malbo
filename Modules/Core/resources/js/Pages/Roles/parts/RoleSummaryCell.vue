<script setup lang="ts">
import { computed } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import { t } from '@/lib/i18n';

import type { RoleSummary } from './types';

const LIMIT = 12;

const props = defineProps<{ summary: RoleSummary }>();

const visible = computed(() => props.summary.groups.slice(0, LIMIT));
const more = computed(() => Math.max(props.summary.group_count - LIMIT, 0));
</script>

<template>
    <span v-if="summary.total === 0" class="text-sm text-gray-500 dark:text-gray-400">{{ t('No permissions assigned') }}</span>
    <div v-else class="flex max-w-3xl flex-col gap-1.5 py-1">
        <span class="text-xs text-gray-500 dark:text-gray-400">{{ summary.total }} {{ t('Permissions') }} / {{ summary.group_count }} {{ t('Groups') }}</span>
        <span class="flex flex-wrap gap-1">
            <Badge v-for="group in visible" :key="group.label" tone="brand">{{ group.label }} ({{ group.count }})</Badge>
            <Badge v-if="more > 0">+{{ more }} {{ t('more groups') }}</Badge>
        </span>
    </div>
</template>
