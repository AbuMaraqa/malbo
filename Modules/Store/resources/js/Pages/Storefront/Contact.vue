<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import BranchList from '@/Components/storefront/BranchList.vue';
import ContactChannels from '@/Components/storefront/ContactChannels.vue';
import ContactForm from '@/Components/storefront/ContactForm.vue';
import PageHero from '@/Components/storefront/PageHero.vue';
import { btnOutline, chip } from '@/Components/storefront/styles';
import type { StoreBranch, StoreChannels, StoreSocial } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';

defineOptions({ layout: StorefrontLayout });

defineProps<{
    channels: StoreChannels | null;
    socials: StoreSocial[];
    branches: StoreBranch[];
    faqUrl: string | null;
    topics: { value: string; label: string }[];
}>();
</script>

<template>
    <div>
        <PageHero :title="t('Contact us')" :lead="t('We are happy to help with orders, sizes, delivery or anything else.')" />

        <div class="mx-auto grid max-w-310 gap-8 px-4 pt-10 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-12 lg:pt-14">
            <ContactForm :topics="topics" />

            <aside class="space-y-5">
                <ContactChannels v-if="channels" :channels="channels" />

                <div v-if="socials.length" class="rounded-lg border border-line bg-white p-6">
                    <h2 id="socials-title" class="text-[17px] font-bold">{{ t('Follow us') }}</h2>
                    <ul class="mt-4 flex flex-wrap gap-2" aria-labelledby="socials-title">
                        <li v-for="social in socials" :key="social.url"><a :href="social.url" target="_blank" rel="noopener" :class="chip">{{ social.label }}</a></li>
                    </ul>
                </div>

                <div v-if="faqUrl" class="rounded-lg bg-store-50 p-6">
                    <h2 class="text-[17px] font-bold">{{ t('Quick answers') }}</h2>
                    <p class="mt-1.5 text-sm leading-6 text-(--ink-soft)">{{ t('Ordering, payment, delivery and returns: most answers are already in our FAQ.') }}</p>
                    <Link :href="faqUrl" class="mt-4 w-full bg-white" :class="btnOutline">{{ t('Read the FAQ') }}</Link>
                </div>
            </aside>
        </div>

        <BranchList v-if="branches.length" :branches="branches" />
    </div>
</template>
