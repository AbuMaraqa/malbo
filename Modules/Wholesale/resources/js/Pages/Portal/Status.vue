<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import { btnBrand, btnOutline, card } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import PortalShell from './parts/PortalShell.vue';
import type { PortalLink } from './parts/types';

defineOptions({ layout: StorefrontLayout });

/** Where the account's wholesale application stands. Never a price. */
const props = defineProps<{
    merchant: { shop_name: string; status: string; status_label: string; reason: string | null; applied_at: string | null } | null;
    nav: PortalLink[] | null;
    applyUrl: string;
}>();

const explanation = computed(() => {
    switch (props.merchant?.status) {
        case 'pending':
            return t('We are reviewing your application. You will see wholesale prices as soon as it is approved.');
        case 'approved':
            return t('Your wholesale account is active.');
        case 'rejected':
            return t('Your application was not approved.');
        case 'suspended':
            return t('Your wholesale account is suspended. Contact us to reactivate it.');
        default:
            return t('This account is not a wholesale account. To apply, sign out and fill in the join form with your shop\'s email.');
    }
});

function signOut(): void {
    router.post(route('store.account.logout'));
}
</script>

<template>
    <PortalShell :title="t('Wholesale account')" :nav="nav" current="account">
        <section :class="card" class="mx-auto max-w-2xl p-6 sm:p-8">
            <template v-if="merchant">
                <p class="text-sm text-muted">{{ t('Shop') }}</p>
                <h2 class="text-xl font-bold">{{ merchant.shop_name }}</h2>
                <p class="mt-4 inline-flex rounded-full bg-(--sand-light) px-3 py-1 text-sm font-bold">{{ merchant.status_label }}</p>
                <p v-if="merchant.applied_at" class="mt-2 text-sm text-muted">
                    {{ t('Applied on') }} <span dir="ltr">{{ merchant.applied_at }}</span>
                </p>
            </template>

            <p class="mt-4 leading-7 text-(--ink-soft)">{{ explanation }}</p>
            <p v-if="merchant?.reason" class="mt-3 rounded-md border border-line bg-(--sand-light) p-3 text-sm whitespace-pre-line">{{ merchant.reason }}</p>

            <div class="mt-6 flex flex-wrap gap-3">
                <Link v-if="merchant?.status === 'approved'" :href="route('store.wholesale.catalog')" :class="btnBrand">{{ t('Wholesale catalog') }}</Link>
                <template v-if="!merchant">
                    <button type="button" :class="btnOutline" @click="signOut">{{ t('Sign out') }}</button>
                </template>
                <Link :href="route('home')" :class="btnOutline">{{ t('Back to the store') }}</Link>
            </div>
        </section>
    </PortalShell>
</template>
