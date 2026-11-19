<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { t } from '@/lib/i18n';
import { Heart, HeartFilled } from '@/lib/icons';
import { route } from '@/lib/route';

import type { StoreLayoutProps } from './types';

/**
 * The heart on a product card or page. Signed in, it adds or removes the product; a guest is sent
 * to sign in and comes back to this page (`?back=`).
 */
const props = withDefaults(defineProps<{ productId: number; size?: 'sm' | 'md' }>(), { size: 'sm' });

const page = usePage();
const store = computed(() => page.props.store as StoreLayoutProps);
const saved = computed(() => store.value.wishlist.includes(props.productId));
const busy = ref(false);
const label = computed(() => (saved.value ? t('Remove from wishlist') : t('Add to wishlist')));
const loginUrl = computed(() => `${store.value.account.login_url}?back=${encodeURIComponent(page.url)}`);
const box = computed(() => (props.size === 'md' ? 'size-12' : 'size-10'));

function toggle(): void {
    if (busy.value) return;

    busy.value = true;
    router.post(route('store.wishlist.toggle'), { product_id: props.productId }, { preserveScroll: true, preserveState: true, onFinish: () => (busy.value = false) });
}
</script>

<template>
    <button
        v-if="store.account.signed_in"
        type="button"
        :aria-label="label"
        :title="label"
        :aria-pressed="saved"
        :disabled="busy"
        class="z-10 grid cursor-pointer place-items-center rounded-full bg-(--shell) text-ink shadow-sm transition hover:bg-store hover:text-store-contrast disabled:opacity-60 motion-reduce:transition-none"
        :class="box"
        @click.prevent="toggle"
    >
        <HeartFilled v-if="saved" class="size-5 text-(--danger)" aria-hidden="true" />
        <Heart v-else class="size-5" aria-hidden="true" />
    </button>
    <Link
        v-else
        :href="loginUrl"
        :aria-label="t('Sign in to save this product')"
        :title="t('Add to wishlist')"
        class="z-10 grid place-items-center rounded-full bg-(--shell) text-ink shadow-sm transition hover:bg-store hover:text-store-contrast motion-reduce:transition-none"
        :class="box"
    >
        <Heart class="size-5" aria-hidden="true" />
    </Link>
</template>
