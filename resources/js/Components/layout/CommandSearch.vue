<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { computed, nextTick, onMounted, onUnmounted, ref, watch, type Component } from 'vue';

import { can, t } from '@/lib/i18n';
import { ArrowRightUp, LogOut, Moon, Search, SearchX, Settings, Store, Sun, UserCircle } from '@/lib/icons';
import { route } from '@/lib/route';
import { useTheme } from '@/lib/useTheme';

import { fallbackNavIcon, navIcons } from './navIcons';
import { useNavigation } from './useNavigation';

interface Result {
    id: string;
    group: string;
    title: string;
    hint?: string;
    icon: Component;
    run: () => void;
}

const open = defineModel<boolean>('open', { required: true });

const { links, hrefOf } = useNavigation();
const { isDarkMode, toggleTheme } = useTheme();

const query = ref('');
const active = ref(0);
const list = ref<HTMLElement>();

/** Arabic is searched without its marks and letter variants: "إعدادات" finds "اعدادات". */
function normalize(text: string): string {
    return text
        .toLowerCase()
        .replace(/[ً-ٰٟـ]/g, '')
        .replace(/[أإآ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ة/g, 'ه')
        .trim();
}

const all = computed<Result[]>(() => [
    ...links.value.map(({ entry, parent }) => ({
        id: `page:${entry.route}`,
        group: t('Pages'),
        title: t(entry.title),
        hint: parent ? t(parent.title) : undefined,
        icon: navIcons[parent?.icon ?? entry.icon] ?? fallbackNavIcon,
        run: () => router.visit(hrefOf(entry)),
    })),
    { id: 'profile', group: t('Account'), title: t('Profile'), icon: UserCircle, run: () => router.visit(route('profile.index')) },
    ...(can('Setting View List') ? [{ id: 'settings', group: t('Account'), title: t('System Settings'), icon: Settings, run: () => router.visit(route('settings')) }] : []),
    { id: 'store', group: t('Account'), title: t('View store'), icon: Store, run: () => window.open('/', '_blank', 'noopener') },
    { id: 'theme', group: t('Account'), title: isDarkMode.value ? t('Light mode') : t('Dark mode'), icon: isDarkMode.value ? Sun : Moon, run: toggleTheme },
    { id: 'logout', group: t('Account'), title: t('Sign Out'), icon: LogOut, run: () => router.post('/logout') },
]);

const results = computed(() => {
    const words = normalize(query.value).split(/\s+/).filter(Boolean);

    return all.value.filter((item) => {
        const text = normalize(`${item.title} ${item.hint ?? ''}`);

        return words.every((word) => text.includes(word));
    });
});

const groups = computed(() => {
    const map = new Map<string, { item: Result; index: number }[]>();
    results.value.forEach((item, index) => map.set(item.group, [...(map.get(item.group) ?? []), { item, index }]));

    return [...map.entries()];
});

watch(query, () => (active.value = 0));
watch(open, (value) => {
    if (value) {
        query.value = '';
        active.value = 0;
    }
});

function choose(item: Result | undefined): void {
    if (!item) return;
    open.value = false;
    item.run();
}

function move(step: number): void {
    const count = results.value.length;
    if (!count) return;
    active.value = (active.value + step + count) % count;
    nextTick(() => list.value?.querySelector(`[data-index="${active.value}"]`)?.scrollIntoView({ block: 'nearest' }));
}

/** Ctrl+K (Cmd+K) anywhere, or "/" outside a field, opens the search. */
function onKeydown(event: KeyboardEvent): void {
    const typing = event.target instanceof HTMLElement && (event.target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName));

    if ((event.key.toLowerCase() === 'k' && (event.ctrlKey || event.metaKey)) || (event.key === '/' && !typing)) {
        event.preventDefault();
        open.value = !open.value;
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-[80] bg-gray-950/30 backdrop-blur-[3px] data-[state=open]:animate-fade-in" />
            <DialogContent
                class="fixed start-1/2 top-[10vh] z-[90] flex max-h-[72vh] w-[calc(100vw-2rem)] max-w-xl flex-col overflow-hidden rounded-xl border border-line bg-card shadow-theme-xl outline-none data-[state=open]:animate-pop-in ltr:-translate-x-1/2 rtl:translate-x-1/2"
            >
                <DialogTitle class="sr-only">{{ t('Search') }}</DialogTitle>
                <DialogDescription class="sr-only">{{ t('Find a page or an action by its name.') }}</DialogDescription>

                <label class="flex items-center gap-3 border-b border-line px-4">
                    <Search class="size-5 shrink-0 text-gray-400" />
                    <input
                        v-model="query"
                        type="text"
                        class="h-14 flex-1 bg-transparent text-[15px] text-ink outline-none placeholder:text-gray-400"
                        :placeholder="t('Search pages and actions...')"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-controls="command-results"
                        :aria-activedescendant="results.length ? `command-${active}` : undefined"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter.prevent="choose(results[active])"
                    />
                    <kbd class="kbd">Esc</kbd>
                </label>

                <div id="command-results" ref="list" class="custom-scrollbar min-h-0 flex-1 overflow-y-auto p-2" role="listbox">
                    <section v-for="[group, items] in groups" :key="group" class="py-1">
                        <h3 class="px-2.5 pt-1 pb-1.5 text-xs font-medium text-gray-400 dark:text-gray-500">{{ group }}</h3>
                        <button
                            v-for="{ item, index } in items"
                            :id="`command-${index}`"
                            :key="item.id"
                            type="button"
                            role="option"
                            :data-index="index"
                            :aria-selected="index === active"
                            class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-start text-sm"
                            :class="index === active ? 'bg-gray-100 text-gray-900 dark:bg-white/5 dark:text-white' : 'text-gray-700 dark:text-gray-300'"
                            @mousemove="active = index"
                            @click="choose(item)"
                        >
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-line bg-card" :class="index === active ? 'text-brand-500' : 'text-gray-500 dark:text-gray-400'">
                                <component :is="item.icon" class="size-4.5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ item.title }}</span>
                                <span v-if="item.hint" class="block truncate text-xs text-muted">{{ item.hint }}</span>
                            </span>
                            <ArrowRightUp v-if="index === active" class="size-4 shrink-0 text-gray-400 rtl:-scale-x-100" />
                        </button>
                    </section>

                    <div v-if="!results.length" class="flex flex-col items-center px-4 py-12 text-center">
                        <SearchX class="size-8 text-gray-300 dark:text-gray-600" />
                        <p class="mt-3 text-sm font-medium text-gray-900 dark:text-white">{{ t('No results') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ t('Try another word.') }}</p>
                    </div>
                </div>

                <footer class="hidden items-center gap-4 border-t border-line bg-surface px-4 py-2.5 text-xs text-muted sm:flex">
                    <span class="flex items-center gap-1.5"><kbd class="kbd">↑</kbd><kbd class="kbd">↓</kbd>{{ t('to move') }}</span>
                    <span class="flex items-center gap-1.5"><kbd class="kbd">Enter</kbd>{{ t('to open') }}</span>
                    <span class="ms-auto flex items-center gap-1.5" dir="ltr"><kbd class="kbd">Ctrl</kbd><kbd class="kbd">K</kbd></span>
                </footer>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
