<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { Check } from '@/lib/icons';
import { buttonVariants } from '@/tailadmin/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/tailadmin/ui/dropdown-menu';

interface Locale {
    code: string;
    native: string;
    flag: string;
    dir: 'rtl' | 'ltr';
}

const page = usePage();

const locales = computed(() => page.props.locales as Locale[]);
const current = computed(() => locales.value.find((locale) => locale.code === page.props.locale));

/** The same page in another language. A full load: the texts and the direction change with it. */
function urlFor(code: string): string {
    const codes = locales.value.map((locale) => locale.code).join('|');

    return page.url.replace(new RegExp(`^/(${codes})(?=/|$|\\?)`), `/${code}`);
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :class="buttonVariants({ variant: 'outline', size: 'icon-lg' })" :aria-label="t('Change language')" :title="current?.native">
            <img v-if="current" :src="`/images/flags/${current.flag}.svg`" alt="" class="size-5 rounded-full object-cover" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" :side-offset="12" class="min-w-52 p-2">
            <a
                v-for="locale in locales"
                :key="locale.code"
                :href="urlFor(locale.code)"
                :hreflang="locale.code"
                :lang="locale.code"
                :dir="locale.dir"
                class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium"
                :class="locale.code === page.props.locale ? 'bg-brand-50 text-brand-500 dark:bg-brand-500/12 dark:text-brand-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5'"
                :aria-current="locale.code === page.props.locale ? 'true' : undefined"
            >
                <img :src="`/images/flags/${locale.flag}.svg`" alt="" class="size-5 shrink-0 rounded-full object-cover" />
                <span class="flex-1 truncate">{{ locale.native }}</span>
                <Check v-if="locale.code === page.props.locale" class="size-4 shrink-0" />
            </a>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
