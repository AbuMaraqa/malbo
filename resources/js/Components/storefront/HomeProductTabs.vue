<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, useId } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import ProductGrid from './ProductGrid.vue';
import SectionHeading from './SectionHeading.vue';
import { btnOutline, container } from './styles';
import type { StoreCard } from './types';

/**
 * The home's products under tabs: new arrivals, best sellers and the store's picks. A list that is
 * empty, or holds the same products in the same order as a tab before it (nothing has sold yet), gets
 * no tab. Arrow keys move between the tabs (ARIA tabs).
 */
const props = defineProps<{ arrivals: StoreCard[]; bestSellers: StoreCard[]; featured: StoreCard[] }>();

const uid = useId();
const tabs = computed(() => {
    const all = [
        { key: 'arrivals', label: t('New arrivals'), cards: props.arrivals, more: route('store.shop') },
        { key: 'best', label: t('Best sellers'), cards: props.bestSellers, more: route('store.shop', { sort: 'best_selling' }) },
        { key: 'featured', label: t('Store picks'), cards: props.featured, more: route('store.shop', { sort: 'featured' }) },
    ];
    const ids = (cards: StoreCard[]) => cards.map((card) => card.id).join(',');

    return all.filter((tab, index) => tab.cards.length && !all.slice(0, index).some((before) => ids(before.cards) === ids(tab.cards)));
});

const selected = ref(0);
const active = computed(() => tabs.value[Math.min(selected.value, tabs.value.length - 1)]);
const buttons = ref<HTMLButtonElement[]>([]);

// The arrow pointing to the end of the line goes to the next tab, in Arabic too: `right` is 1, `left` -1.
function arrow(event: KeyboardEvent, right: 1 | -1): void {
    const rtl = getComputedStyle(event.currentTarget as HTMLElement).direction === 'rtl';

    move(rtl ? -right : right);
}

function move(step: number): void {
    selected.value = (selected.value + step + tabs.value.length) % tabs.value.length;
    buttons.value[selected.value]?.focus();
}
</script>

<template>
    <section v-if="active" data-section="products" :class="container" class="reveal mt-24">
        <SectionHeading :title="t('Our products')" :lead="t('The latest pieces to reach the store.')" />

        <div v-if="tabs.length > 1" role="tablist" :aria-label="t('Our products')" class="mt-8 flex flex-wrap justify-center gap-x-8 gap-y-2" @keydown.right.prevent="arrow($event, 1)" @keydown.left.prevent="arrow($event, -1)">
            <button
                v-for="(tab, index) in tabs"
                :id="`${uid}-tab-${tab.key}`"
                ref="buttons"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="tab === active"
                :aria-controls="`${uid}-panel`"
                :tabindex="tab === active ? 0 : -1"
                class="relative cursor-pointer py-2 text-[0.8125rem] font-semibold ltr:tracking-[0.1em] ltr:uppercase transition-colors after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:bg-(--gold) after:transition-transform"
                :class="tab === active ? 'text-(--gold-ink) after:scale-x-100' : 'text-ink after:scale-x-0 hover:text-store-600'"
                @click="selected = index"
            >
                {{ tab.label }}
            </button>
        </div>

        <div :id="`${uid}-panel`" :role="tabs.length > 1 ? 'tabpanel' : undefined" :aria-labelledby="tabs.length > 1 ? `${uid}-tab-${active.key}` : undefined" class="mt-10">
            <ProductGrid :key="active.key" :cards="active.cards" />
        </div>

        <div class="mt-12 text-center">
            <Link :href="active.more" :class="btnOutline">{{ t('View all products') }}</Link>
        </div>
    </section>
</template>
