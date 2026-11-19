<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { ShoppingBag } from '@/lib/icons';

/** The header's cart link, with how many pieces the cart holds. */
const props = defineProps<{ count: number; url: string }>();

const label = computed(() => (props.count > 0 ? t('Cart, :count items', { count: props.count }) : t('Cart')));
</script>

<template>
    <Link :href="url" :aria-label="label" class="relative grid size-11 place-items-center rounded-full transition-colors hover:bg-(--sand) hover:text-store-600">
        <ShoppingBag class="size-5.5" aria-hidden="true" />
        <span
            v-if="count > 0"
            aria-hidden="true"
            class="absolute -end-0.5 top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-(--gold) px-1 text-[0.6875rem] leading-none font-bold text-(--night)"
        >
            {{ count > 99 ? '99+' : count }}
        </span>
    </Link>
</template>
