<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import CartLineRow from '@/Components/storefront/CartLineRow.vue';
import PageHero from '@/Components/storefront/PageHero.vue';
import { btnBrand, btnOutline, container } from '@/Components/storefront/styles';
import type { CartLine } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{
    lines: CartLine[];
    summary: { subtotal: number; subtotal_label: string; count: number; can_checkout: boolean; checkout_url: string };
}>();

const page = usePage();
const busy = ref(false);
const error = computed(() => (page.props.errors as Record<string, string>).quantity ?? null);
const blocked = computed(() => props.lines.some((line) => !line.available));

function finish(): void {
    busy.value = false;
}

function setQuantity(line: CartLine, quantity: number): void {
    busy.value = true;
    router.put(route('store.cart.update', line.id), { quantity }, { preserveScroll: true, onFinish: finish });
}

function remove(line: CartLine): void {
    busy.value = true;
    router.delete(route('store.cart.remove', line.id), { preserveScroll: true, onFinish: finish });
}
</script>

<template>
    <PageHero :title="t('Your cart')" />
    <div :class="container" class="pt-12">
        <div v-if="!lines.length" class="rounded-lg border border-dashed border-line p-12 text-center">
            <p class="text-lg">{{ t('Your cart is empty.') }}</p>
            <Link :href="route('store.shop')" class="mt-6" :class="btnBrand">{{ t('Continue shopping') }}</Link>
        </div>

        <div v-else class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_22rem]">
            <section :aria-label="t('Items in your cart')" class="rounded-lg border border-line bg-white px-5">
                <p v-if="error" role="alert" class="pt-4 text-sm font-bold text-(--danger)">{{ error }}</p>
                <ul class="divide-y divide-line">
                    <CartLineRow v-for="line in lines" :key="line.id" :line="line" :busy="busy" @quantity="setQuantity(line, $event)" @remove="remove(line)" />
                </ul>
            </section>

            <aside class="rounded-lg border border-line bg-white p-6 lg:sticky lg:top-36" aria-labelledby="cart-summary">
                <h2 id="cart-summary" class="text-xl font-bold">{{ t('Order summary') }}</h2>
                <dl class="mt-5 flex items-center justify-between text-lg">
                    <dt>{{ t('Subtotal') }}</dt>
                    <dd class="font-bold">{{ summary.subtotal_label }}</dd>
                </dl>
                <p class="mt-2 text-sm text-muted">{{ t('Delivery and coupons are worked out at checkout.') }}</p>
                <p v-if="blocked" class="mt-4 text-sm font-bold text-(--danger)">{{ t('Remove or change the items marked above to continue.') }}</p>
                <Link v-if="summary.can_checkout" :href="summary.checkout_url" class="mt-6 w-full" :class="btnBrand">{{ t('Checkout') }}</Link>
                <button v-else type="button" disabled class="mt-6 w-full" :class="btnBrand">{{ t('Checkout') }}</button>
                <Link :href="route('store.shop')" class="mt-3 w-full" :class="btnOutline">{{ t('Continue shopping') }}</Link>
            </aside>
        </div>
    </div>
</template>
