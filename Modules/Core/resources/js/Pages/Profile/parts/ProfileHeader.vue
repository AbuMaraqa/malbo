<script setup lang="ts">
defineProps<{ name: string; email: string; avatarUrl: string; coverUrl: string | null; roles: string[] }>();
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
        <div class="h-40 bg-gray-100 bg-cover bg-center md:h-56 dark:bg-gray-800" :style="coverUrl ? { backgroundImage: `url('${coverUrl}')` } : undefined" />
        <div class="flex flex-col gap-4 px-5 pb-5 sm:flex-row sm:items-end md:px-6">
            <img :src="avatarUrl" alt="" class="-mt-12 size-24 rounded-full border-4 border-white bg-white object-cover md:size-28 dark:border-gray-900" />
            <div class="min-w-0">
                <h2 class="truncate text-xl font-semibold text-gray-800 dark:text-white/90">{{ name }}</h2>
                <p class="truncate text-sm text-gray-500 dark:text-gray-400" dir="ltr">{{ email }}</p>
                <p v-if="roles.length" class="text-sm text-gray-500 dark:text-gray-400">{{ roles.join('، ') }}</p>
            </div>
        </div>
    </section>
</template>
