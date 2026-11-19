<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import AccountShell from '@/Components/storefront/AccountShell.vue';
import AddressForm from '@/Components/storefront/AddressForm.vue';
import { btnBrand } from '@/Components/storefront/styles';
import type { DeliverableCity, SavedAddress } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{ addresses: SavedAddress[]; cities: DeliverableCity[]; limit: number }>();

/** The address being edited, `new` for a new one, or null. */
const editing = ref<number | 'new' | null>(props.addresses.length === 0 ? 'new' : null);
const busy = ref(false);

function makeDefault(address: SavedAddress): void {
    busy.value = true;
    router.put(route('store.account.addresses.default', address.id), {}, { preserveScroll: true, onFinish: () => (busy.value = false) });
}

function remove(address: SavedAddress): void {
    busy.value = true;
    router.delete(route('store.account.addresses.destroy', address.id), { preserveScroll: true, onFinish: () => (busy.value = false) });
}
</script>

<template>
    <AccountShell :title="t('My addresses')" :parents="[{ name: t('My account'), url: route('store.account') }]">
        <p v-if="!cities.length" class="rounded-lg border border-line bg-(--shell) p-5 text-(--ink-soft)">{{ t('Delivery is not available yet. Contact the store to order.') }}</p>

        <section v-if="editing === 'new'" class="rounded-lg border border-line bg-(--shell) p-6" aria-labelledby="new-address">
            <h2 id="new-address" class="mb-5 text-xl font-bold">{{ t('New address') }}</h2>
            <AddressForm :cities="cities" @done="editing = null" @cancel="editing = null" />
        </section>
        <div v-else-if="addresses.length < limit && cities.length">
            <button type="button" :class="btnBrand" @click="editing = 'new'">{{ t('Add an address') }}</button>
        </div>

        <ul class="grid gap-4 md:grid-cols-2">
            <li v-for="address in addresses" :key="address.id" class="rounded-lg border bg-(--shell) p-5" :class="address.is_default ? 'border-store' : 'border-line'">
                <AddressForm v-if="editing === address.id" :cities="cities" :address="address" @done="editing = null" @cancel="editing = null" />
                <template v-else>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-bold">{{ address.label || address.recipient_name }}</h3>
                        <span v-if="address.is_default" class="rounded-full bg-store-50 px-2.5 py-0.5 text-xs font-bold text-store-800">{{ t('Default') }}</span>
                    </div>
                    <p class="mt-2">{{ address.recipient_name }} · <span dir="ltr">{{ address.phone }}</span></p>
                    <p class="text-(--ink-soft)">{{ [address.city ?? t('Choose your city'), address.district].filter(Boolean).join('، ') }}</p>
                    <p class="text-(--ink-soft)">{{ address.address_line }}</p>
                    <div class="mt-4 flex flex-wrap gap-4 text-sm font-bold">
                        <button type="button" class="cursor-pointer text-store-700 underline-offset-4 hover:underline" @click="editing = address.id">{{ t('Edit') }}</button>
                        <button v-if="!address.is_default" type="button" class="cursor-pointer text-store-700 underline-offset-4 hover:underline" :disabled="busy" @click="makeDefault(address)">
                            {{ t('Make default') }}
                        </button>
                        <button type="button" class="cursor-pointer text-(--danger) underline-offset-4 hover:underline" :disabled="busy" @click="remove(address)">{{ t('Delete') }}</button>
                    </div>
                </template>
            </li>
        </ul>
    </AccountShell>
</template>
