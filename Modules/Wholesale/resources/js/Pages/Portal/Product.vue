<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { btnBrand, btnOutline, card } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import PortalShell from './parts/PortalShell.vue';
import SizeRunGrid from './parts/SizeRunGrid.vue';
import { runQuantities } from './parts/sizeRuns';
import type { GridCell, PortalLink, WholesaleProduct } from './parts/types';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{ nav: PortalLink[]; product: WholesaleProduct }>();

const page = usePage();
const error = computed(() => (page.props.errors as Record<string, string>).quantities ?? null);
const busy = ref(false);

const cells = computed(() => props.product.rows.flatMap((row) => row.cells.filter((cell): cell is GridCell => cell !== null)));

/** What is in the cart now: the grid starts from it, and saving replaces it. */
const quantities = ref<Record<number, number>>(Object.fromEntries(cells.value.map((cell) => [cell.id, cell.in_cart])));
const runs = ref<Record<number, number>>({});

/**
 * N runs of a color: N of each of its sizes that can be ordered now, none of the others. N stops at
 * the smallest stock among those sizes, so every size gets the same number (sizeRuns.ts).
 */
function setRuns(key: number, count: number): void {
    const row = props.product.rows.find((item) => item.key === key);

    if (!row) return;

    const run = runQuantities(row, count);
    runs.value = { ...runs.value, [key]: run.runs };
    quantities.value = { ...quantities.value, ...run.quantities };
}

const pieces = computed(() => Object.values(quantities.value).reduce((sum, quantity) => sum + quantity, 0));

/** The quantity break reached by this product's pieces, as the cart will apply it. */
const reachedBreak = computed(() => [...props.product.breaks].reverse().find((item) => pieces.value >= item.min_quantity) ?? null);

function save(): void {
    busy.value = true;
    router.put(route('store.wholesale.cart.set', props.product.id), { quantities: quantities.value }, { preserveScroll: true, onFinish: () => (busy.value = false) });
}
</script>

<template>
    <PortalShell :title="product.name" :nav="nav" current="catalog" :parents="[{ name: t('Wholesale catalog'), url: route('store.wholesale.catalog') }]">
        <div class="grid items-start gap-8 lg:grid-cols-[18rem_1fr]">
            <aside :class="card" class="overflow-hidden">
                <div class="aspect-[4/5] bg-(--sand)">
                    <img v-if="product.image" :src="product.image" :alt="product.name" class="size-full object-cover" />
                </div>
                <dl class="grid gap-2 p-4 text-sm">
                    <div>
                        <dt class="text-muted">{{ product.tier ? t('Your price (:tier)', { tier: product.tier }) : t('Your price') }}</dt>
                        <dd class="text-xl font-bold">
                            <span v-if="product.price_from" class="text-sm font-normal text-muted">{{ t('From') }}</span>
                            {{ product.price_label }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ t('Suggested retail price') }}</dt>
                        <dd class="font-semibold">{{ product.retail_label }}</dd>
                    </div>
                    <div v-if="product.sku">
                        <dt class="text-muted">{{ t('SKU') }}</dt>
                        <dd dir="ltr">{{ product.sku }}</dd>
                    </div>
                </dl>
                <p v-if="product.description" class="border-t border-line p-4 text-sm leading-6 text-(--ink-soft)">{{ product.description }}</p>
            </aside>

            <section class="grid gap-5" :aria-label="t('Order quantities')">
                <p v-if="!product.by_piece" class="rounded-md border border-line bg-(--sand-light) p-3 text-sm">
                    {{ t('This product is sold in full size runs: choose how many runs of each color.') }}
                </p>

                <ul v-if="product.breaks.length" class="flex flex-wrap gap-2 text-sm">
                    <li v-for="item in product.breaks" :key="item.min_quantity" class="rounded-full border px-3 py-1" :class="reachedBreak?.min_quantity === item.min_quantity ? 'border-store bg-store text-store-contrast' : 'border-line bg-white'">
                        {{ item.label }}
                    </li>
                </ul>

                <SizeRunGrid v-model="quantities" :rows="product.rows" :sizes="product.sizes" :by-piece="product.by_piece" :runs="runs" @runs="setRuns" />

                <p v-if="error" role="alert" class="text-sm font-bold text-(--danger)">{{ error }}</p>

                <div class="flex flex-wrap items-center justify-between gap-4">
                    <p class="text-sm">
                        {{ t(':count pieces', { count: pieces }) }}
                        <span v-if="reachedBreak" class="text-store-700">· {{ reachedBreak.label }}</span>
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <Link :href="route('store.wholesale.cart')" :class="btnOutline">{{ t('Wholesale cart') }}</Link>
                        <button type="button" :class="btnBrand" :disabled="busy" @click="save">{{ t('Save to the cart') }}</button>
                    </div>
                </div>
                <p class="text-xs text-muted">{{ t('Prices and quantity discounts are confirmed in the cart, with the stock at that moment.') }}</p>
            </section>
        </div>
    </PortalShell>
</template>
