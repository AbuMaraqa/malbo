<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import StoreBrand from './StoreBrand.vue';
import { chip } from './styles';
import type { StoreLayoutProps } from './types';

const props = defineProps<{ store: StoreLayoutProps }>();

const contact = computed(() => props.store.contact);
const hasContactColumn = computed(() => contact.value !== null || props.store.branches.length > 0);
</script>

<template>
    <footer class="mt-24 border-t border-line bg-white">
        <div class="mx-auto grid max-w-310 gap-10 px-4 pt-14 pb-10 text-[15px] text-(--ink-soft) sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr]">
            <div data-footer="brand">
                <StoreBrand :name="store.name" :logo="store.logo" class="text-xl text-ink" />
                <p v-if="store.tagline" class="mt-3 max-w-xs leading-7">{{ store.tagline }}</p>
                <ul v-if="store.socials.length" class="mt-5 flex flex-wrap gap-2" :aria-label="t('Social links')">
                    <li v-for="social in store.socials" :key="social.url">
                        <a :href="social.url" target="_blank" rel="noopener" :class="chip" class="min-h-9 text-sm">{{ social.label }}</a>
                    </li>
                </ul>
            </div>

            <nav data-footer="pages" aria-labelledby="footer-store">
                <h2 id="footer-store" class="mb-3 text-[15px] font-bold text-ink">{{ t('The store') }}</h2>
                <ul class="space-y-2 leading-relaxed">
                    <li><Link :href="route('store.shop')" class="hover:text-ink">{{ t('All products') }}</Link></li>
                    <li v-for="page in store.footer_pages" :key="page.slug">
                        <Link :href="page.url" class="hover:text-ink">{{ page.title }}</Link>
                    </li>
                    <li><Link :href="route('store.contact')" class="hover:text-ink">{{ t('Contact us') }}</Link></li>
                </ul>
            </nav>

            <nav v-if="store.policy_pages.length" data-footer="policies" aria-labelledby="footer-policies">
                <h2 id="footer-policies" class="mb-3 text-[15px] font-bold text-ink">{{ t('Policies') }}</h2>
                <ul class="space-y-2 leading-relaxed">
                    <li v-for="page in store.policy_pages" :key="page.slug">
                        <Link :href="page.url" class="hover:text-ink">{{ page.title }}</Link>
                    </li>
                </ul>
            </nav>

            <div v-if="hasContactColumn" data-footer="contact">
                <h2 class="mb-3 text-[15px] font-bold text-ink">{{ t('Contact us') }}</h2>
                <ul v-if="contact" class="space-y-2 leading-relaxed">
                    <li v-if="contact.whatsapp">
                        <a :href="`https://wa.me/${contact.whatsapp}`" target="_blank" rel="noopener" class="hover:text-ink">{{ t('WhatsApp') }}: <span dir="ltr">+{{ contact.whatsapp }}</span></a>
                    </li>
                    <li v-if="contact.phone">
                        <a :href="`tel:${contact.phone}`" class="hover:text-ink">{{ t('Phone') }}: <span dir="ltr">{{ contact.phone }}</span></a>
                    </li>
                    <li v-if="contact.email">
                        <a :href="`mailto:${contact.email}`" class="break-all hover:text-ink">{{ contact.email }}</a>
                    </li>
                    <li v-if="contact.address">{{ contact.address }}</li>
                </ul>
                <template v-if="store.branches.length">
                    <h3 data-footer="branches" class="mt-5 mb-2 text-[13px] font-bold text-muted">{{ t('Branches and opening hours') }}</h3>
                    <ul class="space-y-1.5 leading-relaxed">
                        <li v-for="branch in store.branches" :key="branch.id">
                            <span class="font-medium text-ink">{{ branch.name }}</span>
                            <template v-if="branch.hours"> — <span dir="ltr">{{ branch.hours }}</span></template>
                        </li>
                    </ul>
                </template>
            </div>
        </div>

        <div class="mx-auto flex max-w-310 flex-wrap items-center justify-between gap-3 border-t border-line px-4 pt-5 pb-8 text-[13px] text-muted">
            <span>© {{ store.year }} {{ store.name }}. {{ t('All rights reserved.') }}</span>
            <a :href="store.other_locale_url" :hreflang="store.other_locale" :lang="store.other_locale" class="hover:text-ink">{{ store.other_locale_name }}</a>
        </div>
    </footer>
</template>
