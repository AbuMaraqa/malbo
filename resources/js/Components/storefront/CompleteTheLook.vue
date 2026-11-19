<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import PriceRow from './PriceRow.vue';
import SectionHeading from './SectionHeading.vue';
import type { LookItem } from './types';

/**
 * "Complete the look": the pieces staff put with this product. Each is added to the cart from here,
 * in the size the buyer picks (a piece without sizes is added as it is). The server checks it again.
 */
const props = defineProps<{ items: LookItem[] }>();

/** item id => the variant picked. */
const picked = reactive<Record<number, number | ''>>(Object.fromEntries(props.items.map((item) => [item.id, ''])));
const adding = ref<number | null>(null);
const added = ref<number | null>(null);
const errors = reactive<Record<number, string | null>>({});

/** What goes in the cart: the variant picked, or the piece itself when it has no sizes. */
function cartId(item: LookItem): number | null {
    if (!item.variants.length) return item.in_stock ? item.id : null;

    const choice = item.variants.find((variant) => variant.id === picked[item.id]);

    return choice?.in_stock ? choice.id : null;
}

function add(item: LookItem): void {
    const id = cartId(item);
    if (id === null) return;

    adding.value = item.id;
    errors[item.id] = null;
    router.post(
        route('store.cart.add'),
        { product_id: id, quantity: 1 },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => (added.value = item.id),
            onError: (bag) => (errors[item.id] = bag.product_id ?? bag.quantity ?? null),
            onFinish: () => (adding.value = null),
        },
    );
}
</script>

<template>
    <section class="mt-20" aria-labelledby="look-title">
        <SectionHeading id="look-title" :title="t('Complete the look')" />
        <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <li v-for="item in items" :key="item.id" class="flex flex-col rounded-lg border border-line bg-(--shell) p-4">
                <Link :href="item.url" class="block aspect-[4/5] overflow-hidden rounded-md bg-(--sand)">
                    <img v-if="item.image" :src="item.image" :alt="item.name" class="size-full object-cover" loading="lazy" />
                </Link>
                <Link :href="item.url" class="mt-3 font-bold hover:underline">{{ item.name }}</Link>
                <PriceRow :price="item.price" class="mt-1" />
                <div class="mt-auto space-y-2 pt-3">
                    <select
                        v-if="item.variants.length"
                        v-model="picked[item.id]"
                        class="w-full rounded-md border border-line bg-(--shell) px-3 py-2 text-sm"
                        :aria-label="t('Choose a size for :name', { name: item.name })"
                        @change="added = null"
                    >
                        <option value="">{{ t('Choose a size') }}</option>
                        <option v-for="variant in item.variants" :key="variant.id" :value="variant.id" :disabled="!variant.in_stock">
                            {{ variant.in_stock ? variant.label : t(':value (out of stock)', { value: variant.label }) }}
                        </option>
                    </select>
                    <p v-else-if="!item.in_stock" class="text-sm font-semibold text-(--danger)">{{ t('Currently unavailable') }}</p>
                    <button
                        type="button"
                        class="w-full cursor-pointer rounded-md bg-(--ink) px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="cartId(item) === null || adding === item.id"
                        @click="add(item)"
                    >
                        {{ added === item.id ? t('Added to your cart.') : t('Add to cart') }}
                    </button>
                    <p v-if="errors[item.id]" role="alert" class="text-sm font-bold text-(--danger)">{{ errors[item.id] }}</p>
                </div>
            </li>
        </ul>
    </section>
</template>
