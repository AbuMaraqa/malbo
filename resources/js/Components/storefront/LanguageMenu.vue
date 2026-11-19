<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { ChevronDown } from '@/lib/icons';

import type { StoreLanguage } from './types';

/**
 * The language menu: the current language with its flag, opening to every language of the site
 * (config/app.php `locales`). A <details> menu, so it opens before hydration. Choosing a language
 * is a full page load: its texts and its direction come with the page. Hidden with one language.
 */
const props = withDefaults(
    defineProps<{
        languages: StoreLanguage[];
        /** `dark` on the footer's night bar. */
        tone?: 'light' | 'dark';
        /** Opens upwards, for the footer and the bottom of the phones' menu. */
        up?: boolean;
    }>(),
    { tone: 'light', up: false },
);

const menu = ref<HTMLDetailsElement | null>(null);
const current = computed(() => props.languages.find((language) => language.current) ?? props.languages[0]);

function close(): void {
    menu.value?.removeAttribute('open');
}

// A click outside or Escape closes it.
function onPointer(event: PointerEvent): void {
    if (menu.value && !menu.value.contains(event.target as Node)) {
        close();
    }
}

function onKey(event: KeyboardEvent): void {
    if (event.key === 'Escape' && menu.value?.open) {
        close();
        menu.value.querySelector('summary')?.focus();
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', onPointer);
    document.addEventListener('keydown', onKey);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointer);
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <details v-if="languages.length > 1 && current" ref="menu" data-language-menu class="group relative">
        <summary
            :aria-label="t('Language')"
            :title="t('Language')"
            :class="
                cn(
                    'flex cursor-pointer list-none items-center gap-2 rounded-full px-2.5 py-2 text-[0.8125rem] font-semibold transition-colors [&::-webkit-details-marker]:hidden',
                    tone === 'dark' ? 'hover:text-white' : 'hover:bg-(--sand) hover:text-store-600',
                )
            "
        >
            <img :src="current.flag" alt="" class="size-4.5 shrink-0 rounded-full object-cover" />
            <span>{{ current.name }}</span>
            <ChevronDown class="size-3.5 transition-transform group-open:rotate-180" aria-hidden="true" />
        </summary>
        <ul
            :class="
                cn(
                    'absolute end-0 z-50 w-48 rounded-lg border border-line bg-(--shell) p-1.5 text-sm text-(--ink) shadow-[0_24px_48px_-20px_rgb(42_21_26/0.35)]',
                    up ? 'bottom-full mb-2' : 'top-full mt-2',
                )
            "
        >
            <li v-for="language in languages" :key="language.code">
                <a
                    :href="language.url"
                    :hreflang="language.code"
                    :lang="language.code"
                    :aria-current="language.current ? 'true' : undefined"
                    :class="cn('flex items-center gap-2.5 rounded-md px-3 py-2 hover:bg-(--sand-light)', language.current && 'font-semibold text-store-600')"
                >
                    <img :src="language.flag" alt="" class="size-5 shrink-0 rounded-full object-cover" />
                    {{ language.name }}
                </a>
            </li>
        </ul>
    </details>
</template>
