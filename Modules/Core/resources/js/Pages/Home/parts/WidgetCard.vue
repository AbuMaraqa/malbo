<script setup lang="ts">
/** The frame of a dashboard widget, as TailAdmin's ecommerce cards. */
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-5 pt-5 pb-5 sm:px-6 sm:pt-6 dark:border-gray-800 dark:bg-white/3">
        <header class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ title }}</h3>
                <p v-if="description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ description }}</p>
            </div>
            <slot name="actions" />
        </header>
        <slot />
    </section>
</template>
