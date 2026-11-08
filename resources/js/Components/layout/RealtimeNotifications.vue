<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Keeps the bell's count live: renders nothing, and listens only when the server says broadcasting
 * is on (`realtime` prop, App\Support\Realtime). Otherwise the Echo client is never downloaded.
 */
interface RealtimeClient {
    key: string;
    host: string | null;
    port: number;
    tls: boolean;
    channel: string;
}

const emit = defineEmits<{ received: [] }>();

const config = usePage().props.realtime as RealtimeClient | null | undefined;

let leave: (() => void) | undefined;
let unmounted = false;

/** /broadcasting/auth is a `web` route: same CSRF protection as Inertia's own requests. */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

onMounted(async () => {
    if (!config) {
        return;
    }

    try {
        const [{ default: Echo }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')]);

        if (unmounted) {
            return;
        }

        const echo = new Echo({
            broadcaster: 'reverb',
            Pusher,
            key: config.key,
            wsHost: config.host ?? window.location.hostname,
            wsPort: config.port,
            wssPort: config.port,
            forceTLS: config.tls,
            enabledTransports: config.tls ? ['wss'] : ['ws'],
            auth: { headers: { 'X-XSRF-TOKEN': xsrfToken() } },
        } as never);

        echo.private(config.channel).listen('.BroadcastNotificationCreated', () => {
            router.reload({ only: ['unreadNotifications'] });
            emit('received');
        });

        leave = () => echo.disconnect();
    } catch {
        // Live updates are a convenience: the count still refreshes on the next page.
    }
});

onBeforeUnmount(() => {
    unmounted = true;
    leave?.();
});
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>
