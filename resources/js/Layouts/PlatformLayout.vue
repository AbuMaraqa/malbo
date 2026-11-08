<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ConfigProvider } from 'reka-ui';
import { computed, defineAsyncComponent } from 'vue';

import { t } from '@/lib/i18n';

/**
 * The platform host: landing page, sign-up and the platform's own admin. `admin` adds what the
 * admin needs (toasts, confirmations) in chunks of their own, so the public pages stay light and
 * render on the server.
 */
const props = withDefaults(defineProps<{ admin?: boolean; wide?: boolean }>(), { admin: false, wide: false });

const Toasts = defineAsyncComponent(() => import('@/Components/ui/Toasts.vue'));
const ConfirmHost = defineAsyncComponent(() => import('@/Components/ui/ConfirmHost.vue'));

interface Locale {
    code: string;
    native: string;
    dir: 'rtl' | 'ltr';
}

const page = usePage();

const dir = computed(() => page.props.dir as 'rtl' | 'ltr');
const appName = computed(() => page.props.appName as string);
const account = computed(() => page.props.platformAdmin as { name: string; logoutUrl: string } | null);
const locales = computed(() => page.props.locales as Locale[]);
const others = computed(() => locales.value.filter((locale) => locale.code !== page.props.locale));

/** The same page in another language. A full load: the texts and the direction change with it. */
function urlFor(code: string): string {
    const codes = locales.value.map((locale) => locale.code).join('|');

    return page.url.replace(new RegExp(`^/(${codes})(?=/|$|\\?)`), `/${code}`);
}

function logout(): void {
    if (account.value) {
        router.post(account.value.logoutUrl);
    }
}
</script>

<template>
    <ConfigProvider :dir="dir">
        <div class="flex min-h-screen flex-col text-gray-900" :class="props.admin ? 'bg-surface dark:text-white/90' : 'bg-gray-50'">
            <header class="border-b border-gray-200 bg-white">
                <div class="mx-auto flex h-14 items-center justify-between gap-4 px-4" :class="wide ? 'max-w-(--breakpoint-2xl)' : 'max-w-6xl'">
                    <span class="font-semibold">{{ appName }}</span>
                    <div class="flex items-center gap-4 text-sm">
                        <a v-for="locale in others" :key="locale.code" :href="urlFor(locale.code)" :hreflang="locale.code" :lang="locale.code" class="text-gray-600 hover:text-gray-900">{{ locale.native }}</a>
                        <template v-if="account">
                            <span class="text-gray-500">{{ account.name }}</span>
                            <button type="button" class="text-gray-600 hover:text-gray-900" @click="logout">{{ t('Sign out') }}</button>
                        </template>
                    </div>
                </div>
            </header>
            <main class="flex-1"><slot /></main>
        </div>
        <template v-if="admin">
            <Toasts />
            <ConfirmHost />
        </template>
    </ConfigProvider>
</template>
