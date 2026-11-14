<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import StoreField from '@/Components/storefront/StoreField.vue';
import { btnBrand, btnOutline, card, fieldError } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import CartGroupCard from './parts/CartGroupCard.vue';
import CartSummaryCard from './parts/CartSummaryCard.vue';
import PortalShell from './parts/PortalShell.vue';
import type { CartGroup, CartSummary, PortalLink } from './parts/types';

defineOptions({ layout: StorefrontLayout });

interface SavedAddress {
    id: number;
    label: string | null;
    recipient_name: string;
    address_line: string;
    phone: string;
    is_default: boolean;
}

const props = defineProps<{
    nav: PortalLink[];
    groups: CartGroup[];
    summary: CartSummary;
    shop: { name: string; address: string | null; city: string | null };
    addresses: SavedAddress[];
    addressesUrl: string;
    paymentTerms: { value: string; label: string }[];
    /** The merchant's credit when it may order on credit. */
    credit: { limit_label: string; balance_label: string; open_label: string; room_label: string } | null;
    submitToken: string;
}>();

const page = usePage();
/** What the server refused as a whole: a line, a size run, the minimum, the rate limit. */
const problems = computed(() =>
    Object.entries(page.props.errors as Record<string, string>)
        .filter(([key]) => !['address_source', 'address_id', 'notes', 'payment_terms'].includes(key))
        .map(([, message]) => message),
);

const defaultAddress = props.addresses.find((address) => address.is_default) ?? props.addresses[0];

const form = useForm({
    address_source: props.shop.address ? 'shop' : defaultAddress ? 'saved' : 'shop',
    address_id: defaultAddress?.id ?? null,
    notes: '',
    payment_terms: props.paymentTerms[0]?.value ?? '',
    submit_token: props.submitToken,
});

function place(): void {
    form.post(route('store.wholesale.checkout.place'), { preserveScroll: true });
}
</script>

<template>
    <PortalShell :title="t('Wholesale checkout')" :nav="nav" current="cart" :parents="[{ name: t('Wholesale cart'), url: route('store.wholesale.cart') }]">
        <form class="grid items-start gap-8 lg:grid-cols-[1fr_22rem]" novalidate @submit.prevent="place">
            <div class="grid gap-6">
                <ul v-if="problems.length" role="alert" class="grid gap-1 rounded-md border border-(--danger) p-4 text-sm font-bold text-(--danger)">
                    <li v-for="message in problems" :key="message">{{ message }}</li>
                </ul>

                <fieldset :class="card" class="grid gap-3 p-6">
                    <legend class="px-1 text-lg font-bold">{{ t('Delivery address') }}</legend>
                    <label v-if="shop.address" class="flex cursor-pointer items-start gap-3 rounded-md border border-line p-3 has-checked:border-store">
                        <input type="radio" name="address" class="mt-1 accent-(--color-store)" :checked="form.address_source === 'shop'" @change="form.address_source = 'shop'" />
                        <span>
                            <span class="block font-semibold">{{ t('The shop\'s address') }}</span>
                            <span class="block text-sm text-(--ink-soft)">{{ shop.name }} · {{ shop.address }}<template v-if="shop.city"> · {{ shop.city }}</template></span>
                        </span>
                    </label>
                    <label v-for="address in addresses" :key="address.id" class="flex cursor-pointer items-start gap-3 rounded-md border border-line p-3 has-checked:border-store">
                        <input
                            type="radio"
                            name="address"
                            class="mt-1 accent-(--color-store)"
                            :checked="form.address_source === 'saved' && form.address_id === address.id"
                            @change="(form.address_source = 'saved'), (form.address_id = address.id)"
                        />
                        <span>
                            <span class="block font-semibold">{{ address.label || address.recipient_name }}</span>
                            <span class="block text-sm text-(--ink-soft)">{{ address.address_line }} · <span dir="ltr">{{ address.phone }}</span></span>
                        </span>
                    </label>
                    <p v-if="!shop.address && !addresses.length" class="text-sm">{{ t('Add a delivery address to your account first.') }}</p>
                    <Link :href="addressesUrl" class="text-sm font-semibold text-store-700 underline underline-offset-4">{{ t('Manage my addresses') }}</Link>
                    <p v-if="form.errors.address_source || form.errors.address_id" :class="fieldError">{{ form.errors.address_source || form.errors.address_id }}</p>
                </fieldset>

                <fieldset :class="card" class="grid gap-3 p-6">
                    <legend class="px-1 text-lg font-bold">{{ t('Payment terms') }}</legend>
                    <label v-for="terms in paymentTerms" :key="terms.value" class="flex cursor-pointer items-center gap-3 rounded-md border border-line p-3 has-checked:border-store">
                        <input v-model="form.payment_terms" type="radio" :value="terms.value" class="accent-(--color-store)" />
                        <span class="font-semibold">{{ terms.label }}</span>
                    </label>
                    <p v-if="credit && form.payment_terms === 'credit'" class="text-sm text-(--ink-soft)">
                        {{ t('Credit limit :limit · you owe :balance · open orders :open · room left :room', { limit: credit.limit_label, balance: credit.balance_label, open: credit.open_label, room: credit.room_label }) }}
                    </p>
                    <p v-if="form.errors.payment_terms" :class="fieldError">{{ form.errors.payment_terms }}</p>
                </fieldset>

                <div :class="card" class="p-6">
                    <StoreField v-model="form.notes" :label="t('Notes')" :hint="t('optional')" :rows="3" :maxlength="1000" :error="form.errors.notes" />
                </div>

                <section class="grid gap-4" :aria-label="t('Items in your cart')">
                    <CartGroupCard v-for="group in groups" :key="group.product_id" :group="group" :busy="form.processing" :editable="false" />
                </section>
            </div>

            <CartSummaryCard :summary="summary">
                <p class="text-sm text-(--ink-soft)">{{ t('MALBO confirms every wholesale order before preparing it.') }}</p>
                <button type="submit" class="w-full" :class="btnBrand" :disabled="form.processing">{{ t('Place the order') }}</button>
                <Link :href="route('store.wholesale.cart')" class="w-full" :class="btnOutline">{{ t('Back to the cart') }}</Link>
            </CartSummaryCard>
        </form>
    </PortalShell>
</template>
