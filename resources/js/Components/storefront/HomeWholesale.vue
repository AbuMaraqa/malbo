<script setup lang="ts">
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { Boxes, Check } from '@/lib/icons';

import ArrowMark from './ArrowMark.vue';
import { btnBrand, btnLg, container, eyebrow } from './styles';
import type { StoreWholesale } from './types';

/**
 * The home's invitation to the wholesale portal: what merchants get, then the join form, or the
 * portal for an account that is already a merchant. Never a price.
 */
const props = defineProps<{ wholesale: StoreWholesale }>();

const perks = computed(() => [t('Wholesale prices by your merchant level'), t('Order in size runs or by the piece'), t('Follow your orders from your account')]);
// The portal is a storefront of its own: a full page load.
const target = computed(() =>
    props.wholesale.merchant ? { href: props.wholesale.portal_url, label: t('Go to the wholesale portal') } : { href: props.wholesale.apply_url, label: t('Join as a merchant') },
);
</script>

<template>
    <section data-section="wholesale" :class="container" class="reveal mt-24">
        <div class="relative overflow-hidden rounded-xl border border-line bg-(--sand-light)">
            <div aria-hidden="true" class="absolute -end-24 -bottom-32 size-96 rounded-full bg-store opacity-10"></div>
            <div class="relative grid items-center gap-10 px-6 py-12 sm:px-12 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                <div>
                    <p :class="eyebrow">{{ t('For shops and resellers') }}</p>
                    <h2 class="mt-3 text-3xl leading-tight font-bold text-balance sm:text-[2.4rem]">{{ t('Buy wholesale from us') }}</h2>
                    <p class="mt-4 max-w-xl text-[17px] leading-8 text-(--ink-soft)">
                        {{ t('Approved merchants see wholesale prices next to the suggested retail price, and order from the same stock as the store.') }}
                    </p>
                    <a :href="target.href" class="mt-8" :class="[btnBrand, btnLg]">{{ target.label }} <ArrowMark /></a>
                </div>
                <ul class="grid gap-3">
                    <li v-for="perk in perks" :key="perk" class="flex items-center gap-4 rounded-lg border border-line bg-white p-4 font-semibold">
                        <span aria-hidden="true" class="grid size-10 shrink-0 place-items-center rounded-full bg-store text-store-contrast"><Check class="size-5" /></span>
                        {{ perk }}
                    </li>
                    <li class="flex items-center gap-3 px-1 text-sm text-muted">
                        <Boxes class="size-5 shrink-0 text-store-600" aria-hidden="true" />
                        {{ t('We review every application before wholesale prices open.') }}
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
