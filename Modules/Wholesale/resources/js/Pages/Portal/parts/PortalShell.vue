<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import PageHero from '@/Components/storefront/PageHero.vue';
import StoreFlash from '@/Components/storefront/StoreFlash.vue';
import { chip, chipActive, container } from '@/Components/storefront/styles';
import { t } from '@/lib/i18n';

import type { PortalLink } from './types';

/** The frame of a portal page, inside the storefront's layout: its title, the portal's links and the flash. */
defineProps<{ title: string; nav?: PortalLink[] | null; current?: string; parents?: { name: string; url: string }[] }>();
</script>

<template>
    <PageHero :title="title" :parents="parents" />
    <div :class="container" class="pt-8 pb-16">
        <nav v-if="nav?.length" :aria-label="t('Wholesale portal')" class="mb-8 flex flex-wrap gap-2">
            <Link v-for="link in nav" :key="link.key" :href="link.url" :class="link.key === current ? chipActive : chip" :aria-current="link.key === current ? 'page' : undefined">
                {{ link.label }}
            </Link>
        </nav>
        <StoreFlash class="mb-6" />
        <slot />
    </div>
</template>
