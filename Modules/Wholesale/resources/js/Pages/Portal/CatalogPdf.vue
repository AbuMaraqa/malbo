<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { btnBrand, btnOutline, card, field, fieldLabel } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import PortalShell from './parts/PortalShell.vue';
import ShareLinkRow from './parts/ShareLinkRow.vue';
import type { CatalogShareLink, PortalLink } from './parts/types';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{
    nav: PortalLink[];
    categories: { slug: string; name: string }[];
    shares: CatalogShareLink[];
    maxProducts: number;
}>();

const category = ref('');
/** Retail by default: wholesale prices are the merchant's purchase prices. */
const prices = ref<'retail' | 'wholesale'>('retail');
const busy = ref(false);

const downloadUrl = computed(() => route('store.wholesale.catalog-pdf.download', { ...(category.value ? { category: category.value } : {}), prices: prices.value }));

function share(): void {
    if (prices.value === 'wholesale' && !confirm(t('Anyone with this link sees your purchase prices. Create it anyway?'))) return;

    busy.value = true;
    router.post(route('store.wholesale.catalog-pdf.shares.store'), { category: category.value || null, prices: prices.value }, { preserveScroll: true, onFinish: () => (busy.value = false) });
}
</script>

<template>
    <PortalShell :title="t('PDF catalog')" :nav="nav" current="catalog-pdf">
        <section :class="card" class="p-6 sm:p-8" aria-labelledby="pdf-title">
            <h2 id="pdf-title" class="text-xl font-bold">{{ t('Your catalog as a PDF') }}</h2>
            <p class="mt-2 text-(--ink-soft)">
                {{ t('Images, colors, sizes and prices. Up to :count products.', { count: props.maxProducts }) }}
            </p>

            <div class="mt-6 grid gap-4 sm:max-w-md">
                <div>
                    <label for="pdf-category" :class="fieldLabel">{{ t('Category') }}</label>
                    <select id="pdf-category" v-model="category" :class="field">
                        <option value="">{{ t('All categories') }}</option>
                        <option v-for="option in categories" :key="option.slug" :value="option.slug">{{ option.name }}</option>
                    </select>
                </div>
                <fieldset>
                    <legend :class="fieldLabel">{{ t('Prices shown') }}</legend>
                    <div class="grid gap-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-md border border-line p-3 has-checked:border-store">
                            <input v-model="prices" type="radio" value="retail" class="mt-1 accent-(--color-store)" />
                            <span>
                                <span class="block font-semibold">{{ t('Retail prices') }}</span>
                                <span class="block text-sm text-(--ink-soft)">{{ t('The suggested retail price only: for your customers.') }}</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-md border border-line p-3 has-checked:border-store">
                            <input v-model="prices" type="radio" value="wholesale" class="mt-1 accent-(--color-store)" />
                            <span>
                                <span class="block font-semibold">{{ t('My wholesale prices') }}</span>
                                <span class="block text-sm text-(--ink-soft)">{{ t('Your prices with quantity discounts, and the suggested retail price.') }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="prices === 'wholesale'" class="mt-2 rounded-md border border-(--danger) p-3 text-sm font-semibold text-(--danger)" role="alert">
                        {{ t('Anyone with this link sees your purchase prices.') }}
                    </p>
                </fieldset>
                <div class="flex flex-wrap gap-3">
                    <a :href="downloadUrl" :class="btnBrand">{{ t('Download the PDF') }}</a>
                    <button type="button" :class="btnOutline" :disabled="busy" @click="share">{{ t('Create a share link') }}</button>
                </div>
                <p class="text-sm text-(--ink-soft)">{{ t('A share link opens the catalog without signing in for 7 days. You can revoke it at any time.') }}</p>
            </div>
        </section>

        <section :class="card" class="mt-6 p-6 sm:p-8" aria-labelledby="links-title">
            <h2 id="links-title" class="text-xl font-bold">{{ t('Your share links') }}</h2>
            <p v-if="!shares.length" class="mt-3 text-(--ink-soft)">{{ t('No links yet.') }}</p>
            <ul v-else class="mt-2 divide-y divide-line">
                <ShareLinkRow v-for="link in shares" :key="link.id" :share="link" />
            </ul>
        </section>
    </PortalShell>
</template>
