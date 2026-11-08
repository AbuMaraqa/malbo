<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import { btnBrand, btnOutline, helpLink } from './styles';
import type { PageLink } from './types';

/** The other help pages, grouped, and a way to ask. */
defineProps<{ help: { about: PageLink[]; policies: PageLink[] }; whatsapp: string | null }>();
</script>

<template>
    <nav data-help-menu aria-labelledby="help-menu-title" class="rounded-lg border border-line bg-white p-5">
        <h2 id="help-menu-title" class="text-[15px] font-bold">{{ t('Help center') }}</h2>
        <ul v-if="help.about.length" class="mt-3 space-y-0.5 text-[15px]">
            <li v-for="item in help.about" :key="item.slug"><Link :href="item.url" :class="helpLink">{{ item.title }}</Link></li>
        </ul>
        <template v-if="help.policies.length">
            <h3 class="mt-4 text-[13px] font-bold text-muted">{{ t('Policies') }}</h3>
            <ul class="mt-2 space-y-0.5 text-[15px]">
                <li v-for="item in help.policies" :key="item.slug"><Link :href="item.url" :class="helpLink">{{ item.title }}</Link></li>
            </ul>
        </template>
    </nav>

    <div data-help-ask class="rounded-lg bg-store-50 p-5">
        <h2 class="text-[15px] font-bold">{{ t('Still have a question?') }}</h2>
        <p class="mt-1.5 text-sm leading-6 text-(--ink-soft)">{{ t('Our team is happy to help with your order or anything else.') }}</p>
        <div class="mt-4 flex flex-col gap-2">
            <a v-if="whatsapp" :href="`https://wa.me/${whatsapp}`" target="_blank" rel="noopener" class="w-full" :class="btnBrand">{{ t('Chat on WhatsApp') }}</a>
            <Link :href="route('store.contact')" class="w-full" :class="whatsapp ? [btnOutline, 'bg-white'] : btnBrand">{{ t('Contact us') }}</Link>
        </div>
    </div>
</template>
