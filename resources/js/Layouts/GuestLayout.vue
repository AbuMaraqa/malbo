<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const name = computed(() => (page.props.store as { name: string } | null)?.name ?? '');
</script>

<template>
    <main class="grid min-h-screen bg-white lg:grid-cols-2 dark:bg-gray-900">
        <div class="flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-md"><slot /></div>
        </div>
        <div class="relative hidden items-center justify-center overflow-hidden bg-brand-950 lg:flex">
            <img src="/images/shape/grid-01.svg" alt="" class="absolute end-0 top-0 w-full max-w-62.5 xl:max-w-112.5" />
            <img src="/images/shape/grid-01.svg" alt="" class="absolute start-0 bottom-0 w-full max-w-62.5 rotate-180 xl:max-w-112.5" />
            <p class="relative text-3xl font-semibold text-white">{{ name }}</p>
        </div>
    </main>
</template>
