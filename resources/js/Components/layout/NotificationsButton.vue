<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Sheet from '@/Components/ui/Sheet.vue';
import { t } from '@/lib/i18n';
import { Bell, CheckRead } from '@/lib/icons';
import { route } from '@/lib/route';

import NotificationList from './NotificationList.vue';
import RealtimeNotifications from './RealtimeNotifications.vue';

defineProps<{ buttonClass?: string }>();

const page = usePage();
const unread = computed(() => page.props.unreadNotifications as number);
const open = ref(false);
const tab = ref<'all' | 'unread'>('all');
const list = ref<InstanceType<typeof NotificationList>>();

function markAllRead(): void {
    router.post(route('notifications.read'), {}, { preserveScroll: true, preserveState: true, onSuccess: () => list.value?.reload() });
}
</script>

<template>
    <RealtimeNotifications @received="list?.reload()" />
    <button type="button" :class="['relative', buttonClass]" :aria-label="unread ? t('You have :count unread notifications', { count: unread }) : t('Notifications')" @click="open = true">
        <Bell />
        <span v-if="unread" class="absolute top-2 end-2.5 flex size-2">
            <span class="absolute inline-flex size-full animate-ping rounded-full bg-success-400 opacity-75" />
            <span class="relative inline-flex size-2 rounded-full bg-success-500 ring-2 ring-card" />
        </span>
    </button>

    <Sheet v-model:open="open" :title="t('Notifications')">
        <template #toolbar>
            <div class="flex gap-5 border-b border-line px-5" role="tablist">
                <button
                    v-for="item in (['all', 'unread'] as const)"
                    :key="item"
                    type="button"
                    role="tab"
                    :aria-selected="tab === item"
                    class="-mb-px flex items-center gap-1.5 border-b-2 py-3 text-sm font-medium transition-colors"
                    :class="tab === item ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                    @click="tab = item"
                >
                    {{ item === 'all' ? t('All') : t('Unread') }}
                    <span v-if="item === 'unread' && unread" class="tabular rounded-full bg-brand-500 px-1.5 text-[11px] leading-4 text-white">{{ unread }}</span>
                </button>
            </div>
        </template>

        <NotificationList v-if="open" ref="list" :unread-only="tab === 'unread'" />

        <template #footer>
            <Button @click="open = false">{{ t('Close') }}</Button>
            <Button variant="primary" :disabled="!unread" @click="markAllRead"><CheckRead />{{ t('Mark all as read') }}</Button>
        </template>
    </Sheet>
</template>
