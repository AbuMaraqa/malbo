<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { Store } from '@/lib/icons';
import { route } from '@/lib/route';

defineProps<{ showText: boolean }>();

const page = usePage();
const store = computed(() => page.props.store as { name: string } | null);
</script>

<template>
    <Link :href="route('dashboard')" class="flex min-w-0 items-center gap-2.5" :title="store?.name">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-500 text-white shadow-theme-sm">
            <Store class="size-5" />
        </span>
        <span v-if="showText && store" class="truncate text-[17px] font-bold tracking-tight text-gray-900 dark:text-white">{{ store.name }}</span>
    </Link>
</template>
