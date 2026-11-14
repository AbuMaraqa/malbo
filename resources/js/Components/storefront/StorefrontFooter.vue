<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { openConsentBanner } from '@/lib/consent';
import { t } from '@/lib/i18n';
import { Boxes, Letter, MapPin, Phone } from '@/lib/icons';
import { route } from '@/lib/route';

import StoreBrand from './StoreBrand.vue';
import { btnBrand, container } from './styles';
import type { StoreLayoutProps } from './types';
import WhatsAppIcon from './WhatsAppIcon.vue';

const props = defineProps<{ store: StoreLayoutProps }>();

const contact = computed(() => props.store.contact);
// "Cookie settings": only when the store tracks anything (the `marketing` prop is sent).
const page = usePage();
const tracking = computed(() => page.props.marketing !== null && page.props.marketing !== undefined);
const shopCategories = computed(() => props.store.categories.slice(0, 6));
const heading = 'mb-5 text-base font-semibold text-white';
const link = 'transition-colors hover:text-white';
</script>

<template>
    <footer class="mt-24 bg-(--night) text-[15px] text-white/65">
        <div v-if="store.wholesale" data-footer="wholesale" class="border-b border-white/10 bg-(--night-soft)">
            <div :class="container" class="flex flex-wrap items-center justify-between gap-6 py-8">
                <div class="flex max-w-2xl items-start gap-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-full bg-store/20 text-store-300">
                        <Boxes class="size-6" aria-hidden="true" />
                    </span>
                    <div>
                        <h2 class="text-xl font-bold text-white">{{ store.wholesale.merchant ? t('Your wholesale portal') : t('Do you own a shop?') }}</h2>
                        <p class="mt-1.5 leading-7">
                            {{ store.wholesale.merchant ? t('Order at wholesale prices, follow your orders and your account.') : t('Buy from us at wholesale prices. Apply for a merchant account, and we review it quickly.') }}
                        </p>
                    </div>
                </div>
                <!-- The portal is a storefront of its own: a full page load. -->
                <a :href="store.wholesale.merchant ? store.wholesale.portal_url : store.wholesale.apply_url" class="inline-flex min-h-11 items-center justify-center rounded-md border border-white/25 px-6 py-2.5 font-semibold text-white transition-colors hover:border-store hover:bg-store hover:text-store-contrast">
                    {{ store.wholesale.merchant ? t('Wholesale portal') : t('Join as a merchant') }}
                </a>
            </div>
        </div>

        <div v-if="store.members_url" data-footer="members" class="border-b border-white/10">
            <div :class="container" class="flex flex-wrap items-center justify-between gap-6 py-10">
                <div class="max-w-xl">
                    <h2 class="text-2xl font-bold text-white">{{ t('Join the members circle') }}</h2>
                    <p class="mt-2 leading-7">{{ t('Be the first to see new arrivals, and get offers we keep for our members only.') }}</p>
                </div>
                <Link :href="store.members_url" :class="btnBrand">{{ t('Join now') }}</Link>
            </div>
        </div>

        <div :class="container" class="grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1.3fr]">
            <div data-footer="brand">
                <span class="inline-flex rounded-md text-white" :class="{ 'bg-white px-3 py-1.5': store.logo }">
                    <StoreBrand :name="store.name" :logo="store.logo" class="text-2xl" :class="{ 'text-ink': store.logo }" />
                </span>
                <p v-if="store.tagline" class="mt-5 max-w-xs leading-7">{{ store.tagline }}</p>
                <ul v-if="store.socials.length" class="mt-6 flex flex-wrap gap-2" :aria-label="t('Social links')">
                    <li v-for="social in store.socials" :key="social.url">
                        <a
                            :href="social.url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex min-h-9 items-center rounded-md border border-white/15 px-3.5 text-sm transition-colors hover:border-store hover:bg-store hover:text-store-contrast"
                        >
                            {{ social.label }}
                        </a>
                    </li>
                </ul>
            </div>

            <nav data-footer="shop" aria-labelledby="footer-shop">
                <h2 id="footer-shop" :class="heading">{{ t('Categories') }}</h2>
                <ul class="space-y-3">
                    <li><Link :href="route('store.shop')" :class="link">{{ t('All products') }}</Link></li>
                    <li v-for="category in shopCategories" :key="category.id">
                        <Link :href="category.url" :class="link">{{ category.name }}</Link>
                    </li>
                    <li v-if="store.categories_url"><Link :href="store.categories_url" :class="link">{{ t('Categories') }}</Link></li>
                </ul>
            </nav>

            <nav data-footer="pages" aria-labelledby="footer-store">
                <h2 id="footer-store" :class="heading">{{ t('The store') }}</h2>
                <ul class="space-y-3">
                    <li v-for="page in store.footer_pages" :key="page.slug">
                        <Link :href="page.url" :class="link">{{ page.title }}</Link>
                    </li>
                    <li><Link :href="route('store.track')" :class="link">{{ t('Track your order') }}</Link></li>
                    <li><Link :href="store.account.signed_in ? store.account.account_url : store.account.login_url" :class="link">{{ t('My account') }}</Link></li>
                    <li><Link :href="route('store.contact')" :class="link">{{ t('Contact us') }}</Link></li>
                    <li v-for="page in store.policy_pages" :key="page.slug" data-footer="policies">
                        <Link :href="page.url" :class="link">{{ page.title }}</Link>
                    </li>
                </ul>
            </nav>

            <div data-footer="contact">
                <h2 :class="heading">{{ t('Contact us') }}</h2>
                <ul v-if="contact" class="space-y-3.5">
                    <li v-if="contact.whatsapp">
                        <a :href="`https://wa.me/${contact.whatsapp}`" target="_blank" rel="noopener" class="flex items-center gap-3" :class="link">
                            <WhatsAppIcon class="size-4.5 shrink-0 text-store-400" />
                            <span dir="ltr">+{{ contact.whatsapp }}</span>
                        </a>
                    </li>
                    <li v-if="contact.phone">
                        <a :href="`tel:${contact.phone}`" class="flex items-center gap-3" :class="link">
                            <Phone class="size-4.5 shrink-0 text-store-400" aria-hidden="true" />
                            <span dir="ltr">{{ contact.phone }}</span>
                        </a>
                    </li>
                    <li v-if="contact.email">
                        <a :href="`mailto:${contact.email}`" class="flex items-center gap-3 break-all" :class="link">
                            <Letter class="size-4.5 shrink-0 text-store-400" aria-hidden="true" />
                            {{ contact.email }}
                        </a>
                    </li>
                    <li v-if="contact.address" class="flex items-start gap-3 leading-7">
                        <MapPin class="mt-1 size-4.5 shrink-0 text-store-400" aria-hidden="true" />
                        {{ contact.address }}
                    </li>
                </ul>
                <template v-if="store.branches.length">
                    <h3 data-footer="branches" class="mt-6 mb-2.5 text-[13px] font-semibold tracking-wide text-white/90">{{ t('Branches and opening hours') }}</h3>
                    <ul class="space-y-1.5 text-sm leading-relaxed">
                        <li v-for="branch in store.branches" :key="branch.id">
                            <span class="text-white/85">{{ branch.name }}</span>
                            <template v-if="branch.hours"> — <span dir="ltr">{{ branch.hours }}</span></template>
                        </li>
                    </ul>
                </template>
                <p v-if="!contact && !store.branches.length">
                    <Link :href="route('store.contact')" :class="link">{{ t('Send us a message') }}</Link>
                </p>
            </div>
        </div>

        <div class="border-t border-white/10">
            <div :class="container" class="flex flex-wrap items-center justify-between gap-3 py-6 text-[13px]">
                <span>© {{ store.year }} {{ store.name }}. {{ t('All rights reserved.') }}</span>
                <button v-if="tracking" type="button" class="cursor-pointer" :class="link" @click="openConsentBanner">{{ t('Cookie settings') }}</button>
                <a :href="store.other_locale_url" :hreflang="store.other_locale" :lang="store.other_locale" :class="link">{{ store.other_locale_name }}</a>
            </div>
        </div>
    </footer>
</template>
