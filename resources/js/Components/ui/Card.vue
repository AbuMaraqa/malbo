<script setup lang="ts">
defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <section class="rounded-xl border border-line bg-card shadow-card">
        <header v-if="title || $slots.actions" class="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
            <div class="min-w-0">
                <h2 v-if="title" class="text-base font-semibold text-gray-900 dark:text-white">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm text-muted">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
        </header>
        <div data-card-body class="p-5"><slot /></div>
        <footer v-if="$slots.footer" class="flex items-center justify-center gap-2 border-t border-line px-5 py-3"><slot name="footer" /></footer>
    </section>
</template>
