<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

import CartReminderOptIn from '@/Components/storefront/CartReminderOptIn.vue';
import CheckoutSummary from '@/Components/storefront/CheckoutSummary.vue';
import PageHero from '@/Components/storefront/PageHero.vue';
import StoreField from '@/Components/storefront/StoreField.vue';
import StoreSelect from '@/Components/storefront/StoreSelect.vue';
import { btnBrand, btnOutline, container, field, fieldError, fieldLabel } from '@/Components/storefront/styles';
import type { CheckoutLine, CheckoutQuote, DeliverableCity, SavedAddress } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{
    lines: CheckoutLine[];
    cities: DeliverableCity[];
    quote: CheckoutQuote;
    paymentMethods: { value: string; label: string }[];
    submitToken: string;
    /** Signed in: the account's details, and its addresses the store delivers to (the default first). */
    account?: { name: string; email: string; phone: string | null } | null;
    addresses?: SavedAddress[];
}>();

const page = usePage();
const savedAddresses = computed(() => props.addresses ?? []);
const startAddress = savedAddresses.value.find((address) => address.is_default) ?? savedAddresses.value[0] ?? null;

const form = useForm({
    submit_token: props.submitToken,
    name: startAddress?.recipient_name ?? props.account?.name ?? '',
    phone: startAddress?.phone ?? props.account?.phone ?? '',
    email: props.account?.email ?? '',
    city_id: startAddress?.city_id ?? (null as number | null),
    district_id: startAddress?.district_id ?? (null as number | null),
    address: startAddress?.address_line ?? '',
    notes: '',
    coupon: '',
    payment_method: props.paymentMethods[0]?.value ?? 'cash',
    save_address: false,
    address_label: '',
});

/** The saved address the form was filled from, or `new` for one typed here. */
const chosenAddress = ref<number | 'new'>(startAddress?.id ?? 'new');

function useAddress(choice: number | 'new'): void {
    chosenAddress.value = choice;
    const address = savedAddresses.value.find((saved) => saved.id === choice);

    form.name = address?.recipient_name ?? props.account?.name ?? form.name;
    form.phone = address?.phone ?? props.account?.phone ?? form.phone;
    form.city_id = address?.city_id ?? null;
    form.address = address?.address_line ?? '';
    // After the city, whose watcher clears an area of another city.
    nextTick(() => (form.district_id = address?.district_id ?? null));
}

/** "Remind me about my cart": saves the contact as the buyer leaves a field of this section. */
const reminder = ref<InstanceType<typeof CartReminderOptIn> | null>(null);

/** The code the summary was last priced with; typing alone does not change the total. */
const appliedCoupon = ref('');
const updating = ref(false);

const districts = computed(() => props.cities.find((city) => city.value === form.city_id)?.districts ?? []);

/** Stock or availability problems, one per cart line. */
const lineErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key === 'items' || key.startsWith('items.'))
        .map(([, message]) => message),
);

const flashError = computed(() => (page.props.flash as { error?: string | null } | undefined)?.error ?? null);

function requote(): void {
    updating.value = true;
    router.reload({
        data: { city_id: form.city_id ?? undefined, coupon: appliedCoupon.value || undefined },
        only: ['quote'],
        preserveUrl: true,
        showProgress: false,
        onFinish: () => (updating.value = false),
    });
}

watch(
    () => form.city_id,
    () => {
        if (!districts.value.some((district) => district.value === form.district_id)) form.district_id = null;
        requote();
    },
);

// A saved address filled the city in: the delivery fee follows it.
onMounted(() => {
    if (form.city_id !== null) requote();
});

function applyCoupon(): void {
    appliedCoupon.value = form.coupon.trim();
    requote();
}

function removeCoupon(): void {
    form.coupon = '';
    appliedCoupon.value = '';
    requote();
}

function place(): void {
    if (form.processing) return;

    // Only a code the buyer applied (and saw priced) goes with the order, never text left in the box.
    form.transform((data) => ({ ...data, coupon: appliedCoupon.value || null })).post(route('store.checkout.place'), {
        preserveScroll: (response) => Object.keys(response.props.errors ?? {}).length > 0,
    });
}
</script>

<template>
    <PageHero :title="t('Checkout')" :parents="[{ name: t('Your cart'), url: route('store.cart') }]" />
    <div :class="container" class="pt-12">
        <form class="grid items-start gap-8 lg:grid-cols-[1fr_24rem]" novalidate @submit.prevent="place">
            <div class="grid gap-6">
                <div v-if="lineErrors.length || flashError" role="alert" class="rounded-lg border border-(--danger) bg-(--shell) p-5 text-(--danger)">
                    <p class="font-bold">{{ t('Some items cannot be ordered as they are:') }}</p>
                    <ul class="mt-2 list-disc ps-5 text-sm">
                        <li v-for="message in lineErrors" :key="message">{{ message }}</li>
                        <li v-if="flashError">{{ flashError }}</li>
                    </ul>
                    <Link :href="route('store.cart')" class="mt-3 inline-block text-sm font-bold underline underline-offset-4">{{ t('Back to your cart') }}</Link>
                </div>

                <section class="rounded-lg border border-line bg-(--shell) p-6" aria-labelledby="contact-title" @focusout="reminder?.save()">
                    <h2 id="contact-title" class="text-xl font-bold">{{ t('Your details') }}</h2>
                    <p v-if="!account" class="mt-2 text-sm text-(--ink-soft)">
                        {{ t('Have an account?') }}
                        <Link :href="`${route('store.account.login')}?back=${encodeURIComponent(page.url)}`" class="font-bold text-store-700 underline underline-offset-4">{{ t('Sign in') }}</Link>
                        {{ t('or continue as a guest.') }}
                    </p>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <StoreField v-model="form.name" class="sm:col-span-2" :label="t('Full name')" autocomplete="name" required :maxlength="120" :error="form.errors.name" />
                        <StoreField v-model="form.phone" :label="t('Phone')" type="tel" autocomplete="tel" inputmode="tel" dir="ltr" required :maxlength="40" :error="form.errors.phone" />
                        <StoreField v-model="form.email" :label="t('Email')" :hint="t('optional')" type="email" autocomplete="email" dir="ltr" :maxlength="160" :error="form.errors.email" />
                        <CartReminderOptIn ref="reminder" :email="form.email" :phone="form.phone" />
                    </div>
                </section>

                <section class="rounded-lg border border-line bg-(--shell) p-6" aria-labelledby="delivery-title">
                    <h2 id="delivery-title" class="text-xl font-bold">{{ t('Delivery') }}</h2>
                    <p v-if="!cities.length" class="mt-3 text-(--danger)">{{ t('Delivery is not available yet. Contact the store to order.') }}</p>
                    <div v-if="savedAddresses.length" class="mt-5 grid gap-3" role="radiogroup" :aria-label="t('My addresses')">
                        <label
                            v-for="address in savedAddresses"
                            :key="address.id"
                            class="flex cursor-pointer items-start gap-3 rounded-md border border-line p-4 has-checked:border-store"
                        >
                            <input type="radio" name="saved_address" :checked="chosenAddress === address.id" class="mt-1 size-4 accent-(--color-store)" @change="useAddress(address.id)" />
                            <span>
                                <span class="font-bold">{{ address.label || address.recipient_name }}</span>
                                <span class="block text-sm text-(--ink-soft)">{{ [address.city, address.district, address.address_line].filter(Boolean).join('، ') }}</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-md border border-line p-4 has-checked:border-store">
                            <input type="radio" name="saved_address" :checked="chosenAddress === 'new'" class="size-4 accent-(--color-store)" @change="useAddress('new')" />
                            <span class="font-bold">{{ t('A new address') }}</span>
                        </label>
                    </div>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <StoreSelect v-model="form.city_id" :label="t('City')" :placeholder="t('Choose your city')" :options="cities" required :error="form.errors.city_id" />
                        <StoreSelect
                            v-if="districts.length"
                            v-model="form.district_id"
                            :label="t('Area')"
                            :placeholder="t('Choose your area')"
                            :options="districts"
                            required
                            :error="form.errors.district_id"
                        />
                        <StoreField v-model="form.address" class="sm:col-span-2" :label="t('Address')" :hint="t('street, building, landmark')" autocomplete="street-address" required :maxlength="500" :error="form.errors.address" />
                        <StoreField v-model="form.notes" class="sm:col-span-2" :label="t('Notes')" :hint="t('optional')" :rows="3" :maxlength="1000" :error="form.errors.notes" />
                        <template v-if="account && chosenAddress === 'new'">
                            <label class="flex cursor-pointer items-center gap-2 sm:col-span-2">
                                <input v-model="form.save_address" type="checkbox" class="size-4 accent-(--color-store)" />
                                <span>{{ t('Save this address to my account') }}</span>
                            </label>
                            <StoreField
                                v-if="form.save_address"
                                v-model="form.address_label"
                                :label="t('Address name')"
                                :hint="t('optional, e.g. Home')"
                                :maxlength="60"
                                :error="form.errors.address_label"
                            />
                        </template>
                    </div>
                </section>

                <section class="rounded-lg border border-line bg-(--shell) p-6" aria-labelledby="coupon-title">
                    <h2 id="coupon-title" class="text-xl font-bold">{{ t('Coupon') }}</h2>
                    <label for="coupon" :class="fieldLabel" class="sr-only">{{ t('Coupon code') }}</label>
                    <div class="mt-4 flex gap-3">
                        <input
                            id="coupon"
                            v-model="form.coupon"
                            type="text"
                            dir="ltr"
                            maxlength="50"
                            autocomplete="off"
                            :placeholder="t('Coupon code')"
                            class="text-start"
                            :class="field"
                            :aria-invalid="form.errors.coupon || quote.coupon?.valid === false ? true : undefined"
                            aria-describedby="coupon-state"
                            @keydown.enter.prevent="applyCoupon"
                        />
                        <button type="button" :class="btnOutline" :disabled="!form.coupon.trim() || updating" @click="applyCoupon">{{ t('Apply') }}</button>
                    </div>
                    <div id="coupon-state" aria-live="polite">
                        <p v-if="form.errors.coupon" :class="fieldError">{{ form.errors.coupon }}</p>
                        <p v-else-if="quote.coupon && !quote.coupon.valid" :class="fieldError">{{ quote.coupon.message }}</p>
                        <p v-else-if="quote.coupon?.valid" class="mt-2 flex items-center gap-3 text-sm font-bold text-store-700">
                            {{ t('Coupon :code applied.', { code: quote.coupon.code }) }}
                            <button type="button" class="cursor-pointer text-muted underline underline-offset-4" @click="removeCoupon">{{ t('Remove') }}</button>
                        </p>
                    </div>
                </section>

                <section class="rounded-lg border border-line bg-(--shell) p-6" aria-labelledby="payment-title">
                    <h2 id="payment-title" class="text-xl font-bold">{{ t('Payment') }}</h2>
                    <div class="mt-4 grid gap-3" role="radiogroup" :aria-label="t('Payment method')">
                        <label v-for="method in paymentMethods" :key="method.value" class="flex cursor-pointer items-center gap-3 rounded-md border border-line p-4 has-checked:border-store">
                            <input v-model="form.payment_method" type="radio" name="payment_method" :value="method.value" class="size-4 accent-(--color-store)" />
                            <span class="font-bold">{{ method.label }}</span>
                        </label>
                    </div>
                    <p v-if="form.payment_method === 'cash'" class="mt-3 text-sm text-(--ink-soft)">{{ t('We call you to confirm the order before we prepare it.') }}</p>
                    <p v-if="form.errors.payment_method" :class="fieldError">{{ form.errors.payment_method }}</p>
                </section>
            </div>

            <!-- Sticky together: a sticky summary alone slid over the submit button below it. -->
            <div class="grid gap-4 lg:sticky lg:top-36 lg:self-start">
                <CheckoutSummary :lines="lines" :quote="quote" :updating="updating" />
                <button type="submit" class="w-full" :class="btnBrand" :disabled="form.processing || updating || !cities.length">
                    {{ form.processing ? t('Placing your order…') : t('Place order') }}
                </button>
            </div>
        </form>
    </div>
</template>
