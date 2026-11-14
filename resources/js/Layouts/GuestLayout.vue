<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { Store } from '@/lib/icons';

const page = usePage();
const name = computed(() => (page.props.store as { name: string } | null)?.name ?? '');
</script>

<template>
    <main class="grid min-h-screen bg-white lg:grid-cols-2 dark:bg-gray-950">
        <div class="flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-md"><slot /></div>
        </div>
        <!-- The brand side: the panel color, a fading grid, the store's name. -->
        <div class="relative m-3 hidden items-center justify-center overflow-hidden rounded-2xl bg-brand-600 lg:flex">
            <div
                class="absolute inset-0 opacity-20 [background-image:linear-gradient(white_1px,transparent_1px),linear-gradient(90deg,white_1px,transparent_1px)] [background-size:44px_44px] [mask-image:radial-gradient(ellipse_at_center,black,transparent_70%)]"
                aria-hidden="true"
            />
            <div class="relative flex flex-col items-center gap-5 text-center">
                <span class="flex size-16 items-center justify-center rounded-2xl bg-white/15 text-white ring-1 ring-white/25 backdrop-blur"><Store class="size-8" /></span>
                <p class="text-3xl font-semibold text-white">{{ name }}</p>
            </div>
        </div>
    </main>
</template>
