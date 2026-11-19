<script setup lang="ts">
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { Check } from '@/lib/icons';

import BrandPetal from './BrandPetal.vue';
import JoinMembersForm from './JoinMembersForm.vue';
import { container } from './styles';

/** The members circle: sign-ups land in the admin's Members list. */
defineProps<{ membership: { title: string; text: string } }>();

const perks = computed(() => [t('First to know about new arrivals'), t('Offers we keep for members'), t('Free to join, leave any time')]);
</script>

<template>
    <section id="members" data-section="membership" :class="container" class="reveal mt-24 scroll-mt-36">
        <div class="relative overflow-hidden rounded-[2rem] bg-(--night) bg-malbo-pattern px-6 py-12 text-white sm:px-12 sm:py-16">
            <BrandPetal aria-hidden="true" class="absolute -end-24 -top-6 w-[34rem] text-white opacity-[0.07]" />
            <div class="relative grid items-center gap-10 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                <div>
                    <span class="inline-block rounded-full bg-(--gold) px-3.5 py-1 text-[13px] font-semibold text-(--night)">{{ t('Members only') }}</span>
                    <h2 class="mt-5 text-3xl leading-tight font-bold text-balance sm:text-[2.6rem]">{{ membership.title }}</h2>
                    <p class="mt-5 max-w-xl text-[17px] leading-8 text-white/70">{{ membership.text }}</p>
                    <ul class="mt-7 space-y-3 text-[15px] text-white/85">
                        <li v-for="perk in perks" :key="perk" class="flex items-center gap-3">
                            <span aria-hidden="true" class="grid size-6 shrink-0 place-items-center rounded-full bg-(--gold) text-(--night)"><Check class="size-3.5" /></span>
                            {{ perk }}
                        </li>
                    </ul>
                </div>
                <JoinMembersForm class="text-ink" />
            </div>
        </div>
    </section>
</template>
