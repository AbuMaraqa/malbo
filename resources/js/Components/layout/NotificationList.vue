<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { X } from '@/lib/icons';
import { onMounted, ref } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

interface NotificationItem {
    id: string;
    title: string | null;
    message: string;
    url: string | null;
    time: string | null;
    read: boolean;
}

const items = ref<NotificationItem[]>([]);
const state = ref<'loading' | 'ready' | 'failed'>('loading');

async function load(): Promise<void> {
    try {
        const response = await fetch(route('notifications.index'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error(String(response.status));
        items.value = ((await response.json()) as { data: NotificationItem[] }).data;
        state.value = 'ready';
    } catch {
        state.value = 'failed';
    }
}

onMounted(load);

function markRead(item: NotificationItem): void {
    router.post(route('notifications.read-one', item.id), {}, { preserveScroll: true, preserveState: true, onSuccess: load });
}

defineExpose({ reload: load });
</script>

<template>
    <p v-if="state === 'loading'" class="p-3 text-sm text-gray-500 dark:text-gray-400">{{ t('Loading...') }}</p>
    <p v-else-if="state === 'failed'" class="p-3 text-sm text-gray-500 dark:text-gray-400">{{ t('Could not load the notifications.') }}</p>
    <ul v-else-if="items.length" class="max-h-80 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800">
        <li v-for="item in items" :key="item.id" class="flex items-start gap-2 p-3">
            <component :is="item.url ? 'a' : 'div'" :href="item.url ?? undefined" class="min-w-0 flex-1">
                <span v-if="item.title" class="block text-sm font-medium text-gray-800 dark:text-white/90">{{ item.title }}</span>
                <span class="block text-sm" :class="item.read ? 'text-gray-500 dark:text-gray-400' : 'text-gray-700 dark:text-gray-300'">{{ item.message }}</span>
                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ item.time }}</span>
            </component>
            <button v-if="!item.read" type="button" class="shrink-0 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" :aria-label="t('Mark as read')" @click="markRead(item)">
                <X class="size-4" />
            </button>
        </li>
    </ul>
    <p v-else class="p-3 text-sm text-gray-500 dark:text-gray-400">{{ t('No notifications') }}</p>
</template>
