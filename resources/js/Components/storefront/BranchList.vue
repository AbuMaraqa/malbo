<script setup lang="ts">
import { t } from '@/lib/i18n';

import { sectionTitle } from './styles';
import type { StoreBranch } from './types';

defineProps<{ branches: StoreBranch[] }>();
</script>

<template>
    <section data-contact="branches" class="mx-auto mt-16 max-w-310 px-4" aria-labelledby="branches-title">
        <h2 id="branches-title" :class="sectionTitle">{{ t('Branches and opening hours') }}</h2>
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="branch in branches" :key="branch.id" class="rounded-lg border border-line bg-white p-6">
                <h3 class="text-lg font-bold">{{ branch.name }}</h3>
                <p v-if="branch.address" class="mt-2 leading-7 text-(--ink-soft)">{{ branch.address }}</p>
                <p v-if="branch.hours" class="mt-2 text-sm"><span class="text-muted">{{ t('Opening hours') }}:</span> <span dir="ltr">{{ branch.hours }}</span></p>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <a v-if="branch.phone" :href="`tel:${branch.phone}`" class="text-store-700" dir="ltr">{{ branch.phone }}</a>
                    <a v-if="branch.map_url" :href="branch.map_url" target="_blank" rel="noopener" class="text-store-700">{{ t('Directions') }}</a>
                </div>
            </li>
        </ul>
    </section>
</template>
