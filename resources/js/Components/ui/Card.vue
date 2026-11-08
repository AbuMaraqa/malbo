<script setup lang="ts">
defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <section class="rounded-2xl border border-line bg-card">
        <header v-if="title || $slots.actions" class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
            <div>
                <h2 v-if="title" class="text-sm font-semibold">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm text-muted">{{ description }}</p>
            </div>
            <slot name="actions" />
        </header>
        <div class="p-5"><slot /></div>
    </section>
</template>
