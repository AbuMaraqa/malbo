<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { Store } from '@/lib/icons';

const page = usePage();
const store = computed(() => page.props.store as { name: string; logo?: string | null } | null);
const name = computed(() => store.value?.name ?? '');
</script>

<template>
    <main class="grid min-h-screen bg-white lg:grid-cols-2 dark:bg-gray-950">
        <div class="flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-md">
                <!-- On a phone the brand side is hidden: the logo comes above the form instead. -->
                <img v-if="store?.logo" :src="store.logo" :alt="name" class="mb-8 h-12 w-auto object-contain lg:hidden" />
                <slot />
            </div>
        </div>
        <!-- The brand side: the panel color under the MALBO pattern, the logo from the system settings (or the MALBO mark), the store's name. -->
        <div class="relative m-3 hidden items-center justify-center overflow-hidden rounded-2xl bg-brand-500 bg-brand-pattern lg:flex">
            <div class="relative flex flex-col items-center gap-5 text-center">
                <!-- A white tile: any logo stays readable on the panel color. -->
                <span v-if="store?.logo" class="flex h-28 min-w-28 items-center justify-center rounded-2xl bg-white p-4 shadow-theme-lg">
                    <img :src="store.logo" :alt="name" class="max-h-20 max-w-56 object-contain" />
                </span>
                <span v-else class="flex size-16 items-center justify-center rounded-2xl bg-white/15 text-white ring-1 ring-white/25 backdrop-blur"><Store class="size-8" /></span>
                <p class="text-3xl font-semibold text-white">{{ name }}</p>
            </div>
        </div>
    </main>
</template>
