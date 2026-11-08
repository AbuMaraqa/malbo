<script setup lang="ts">
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { Check } from '@/lib/icons';

import JoinMembersForm from './JoinMembersForm.vue';
import { glass } from './styles';

/** The members circle: sign-ups land in the admin's Members list. */
defineProps<{ membership: { title: string; text: string } }>();

const perks = computed(() => [t('First to know about new arrivals'), t('Offers we keep for members'), t('Free to join, leave any time')]);
</script>

<template>
    <section id="members" data-section="membership" class="reveal mx-auto mt-28 max-w-310 px-4">
        <div class="relative overflow-hidden rounded-[2rem] bg-(--sand) px-5 py-12 sm:px-12 sm:py-16">
            <div aria-hidden="true" class="blob absolute -start-16 -bottom-32 size-96 rounded-full bg-store opacity-30"></div>
            <div aria-hidden="true" class="blob blob-slow absolute end-[30%] -top-16 size-56 rounded-full bg-(--gold) opacity-60"></div>
            <div class="relative grid items-center gap-10 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                <div>
                    <span class="inline-block rounded-full px-4 py-1.5 text-[13px] font-bold text-store-700" :class="glass">{{ t('Members only') }}</span>
                    <h2 class="mt-5 text-3xl leading-tight font-bold text-balance sm:text-5xl">{{ membership.title }}</h2>
                    <p class="mt-5 max-w-xl text-[17px] leading-8 text-(--ink-soft)">{{ membership.text }}</p>
                    <ul class="mt-7 space-y-3 text-[15px]">
                        <li v-for="perk in perks" :key="perk" class="flex items-center gap-3">
                            <span aria-hidden="true" class="grid size-6 shrink-0 place-items-center rounded-full bg-store text-store-contrast"><Check class="size-3.5" /></span>
                            {{ perk }}
                        </li>
                    </ul>
                </div>
                <JoinMembersForm />
            </div>
        </div>
    </section>
</template>
