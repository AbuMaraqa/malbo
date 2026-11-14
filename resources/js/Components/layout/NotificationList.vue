<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import { t } from '@/lib/i18n';
import { Bell, Check, Inbox } from '@/lib/icons';
import { route } from '@/lib/route';

interface NotificationItem {
    id: string;
    title: string | null;
    message: string;
    url: string | null;
    time: string | null;
    read: boolean;
}

const props = defineProps<{ unreadOnly?: boolean }>();

const items = ref<NotificationItem[]>([]);
const state = ref<'loading' | 'ready' | 'failed'>('loading');

const shown = computed(() => (props.unreadOnly ? items.value.filter((item) => !item.read) : items.value));

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
    <div v-if="state === 'loading'" class="space-y-4 p-5" :aria-label="t('Loading...')" aria-busy="true">
        <div v-for="index in 4" :key="index" class="flex animate-pulse gap-3">
            <span class="size-9 shrink-0 rounded-full bg-gray-100 dark:bg-white/5" />
            <span class="flex-1 space-y-2 pt-1">
                <span class="block h-3 w-3/5 rounded bg-gray-100 dark:bg-white/5" />
                <span class="block h-3 w-4/5 rounded bg-gray-100 dark:bg-white/5" />
            </span>
        </div>
    </div>
    <p v-else-if="state === 'failed'" class="p-5 text-sm text-muted">{{ t('Could not load the notifications.') }}</p>
    <ul v-else-if="shown.length" class="divide-y divide-line">
        <li v-for="item in shown" :key="item.id" class="group relative flex items-start gap-3 px-5 py-4 hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
            <span class="relative flex size-9 shrink-0 items-center justify-center rounded-full" :class="item.read ? 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' : 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'">
                <Bell class="size-4.5" />
                <span v-if="!item.read" class="absolute -top-0.5 -end-0.5 size-2.5 rounded-full bg-success-500 ring-2 ring-card" />
            </span>
            <component :is="item.url ? 'a' : 'div'" :href="item.url ?? undefined" class="min-w-0 flex-1">
                <span v-if="item.title" class="block text-sm font-semibold text-gray-900 dark:text-white">{{ item.title }}</span>
                <span class="mt-0.5 block text-sm" :class="item.read ? 'text-muted' : 'text-gray-700 dark:text-gray-300'">{{ item.message }}</span>
                <span v-if="item.time" class="mt-1 block text-xs text-gray-400 dark:text-gray-500">{{ item.time }}</span>
            </component>
            <button
                v-if="!item.read"
                type="button"
                class="shrink-0 rounded-md p-1 text-gray-400 opacity-0 group-hover:opacity-100 hover:bg-gray-100 hover:text-gray-700 focus:opacity-100 dark:hover:bg-white/5 dark:hover:text-gray-200"
                :title="t('Mark as read')"
                :aria-label="t('Mark as read')"
                @click="markRead(item)"
            >
                <Check class="size-4" />
            </button>
        </li>
    </ul>
    <div v-else class="flex flex-col items-center px-5 py-16 text-center">
        <span class="flex size-14 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-white/5"><Inbox class="size-7" /></span>
        <p class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">{{ t('No notifications') }}</p>
        <p class="mt-1 text-sm text-muted">{{ t('You are all caught up.') }}</p>
    </div>
</template>
