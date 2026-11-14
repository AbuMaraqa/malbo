<script setup lang="ts">
import PageHero from './PageHero.vue';

/** The narrow card of the sign-in, sign-up and password pages. */
defineProps<{ title: string; intro?: string }>();
</script>

<template>
    <PageHero :title="title" />
    <div class="mx-auto max-w-lg px-4 pt-12">
        <section class="rounded-lg border border-line bg-white p-6 shadow-[0_18px_40px_-28px_rgb(22_28_45/0.4)] sm:p-8">
            <p v-if="intro" class="mb-6 leading-7 text-(--ink-soft)">{{ intro }}</p>
            <div>
                <slot />
            </div>
        </section>
        <div class="mt-5 text-center text-(--ink-soft)">
            <slot name="footer" />
        </div>
    </div>
</template>
