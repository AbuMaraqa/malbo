<script setup lang="ts">
/** A titled card of a report: its chart, list or note. */
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <section class="flex h-full flex-col overflow-hidden rounded-xl border border-line bg-card shadow-card">
        <header class="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm text-muted">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex items-center gap-2"><slot name="actions" /></div>
        </header>
        <div class="flex-1 p-5"><slot /></div>
    </section>
</template>
