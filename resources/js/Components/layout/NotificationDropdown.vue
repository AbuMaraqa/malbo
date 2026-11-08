<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from '@/lib/icons';
import { computed, ref } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';
import { buttonVariants } from '@/tailadmin/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuSeparator, DropdownMenuTrigger } from '@/tailadmin/ui/dropdown-menu';

import NotificationList from './NotificationList.vue';
import RealtimeNotifications from './RealtimeNotifications.vue';

const page = usePage();
const unread = computed(() => page.props.unreadNotifications as number);
const list = ref<InstanceType<typeof NotificationList>>();

function markRead(): void {
    router.post(route('notifications.read'), {}, { preserveScroll: true, preserveState: true, onSuccess: () => list.value?.reload() });
}
</script>

<template>
    <RealtimeNotifications @received="list?.reload()" />
    <DropdownMenu>
        <DropdownMenuTrigger :class="buttonVariants({ variant: 'outline', size: 'icon-lg', class: 'relative' })" :aria-label="t('Notifications')">
            <span v-if="unread" class="absolute end-0 -top-0.5 z-10 flex size-2 rounded-full bg-orange-400">
                <span class="absolute inline-flex size-full animate-ping rounded-full bg-orange-400 opacity-75" />
            </span>
            <Bell />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" :side-offset="12" class="w-80 p-3">
            <h5 class="pb-3 text-lg font-semibold text-gray-800 dark:text-gray-200">{{ t('Notifications') }}</h5>
            <DropdownMenuSeparator class="my-0" />
            <NotificationList ref="list" />
            <div class="p-3 text-sm text-gray-500 dark:text-gray-400">
                <p>{{ unread ? t('You have :count unread notifications', { count: unread }) : t('No new notifications') }}</p>
                <button v-if="unread" type="button" class="mt-2 font-medium text-brand-500 hover:underline" @click="markRead">{{ t('Mark all as read') }}</button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
