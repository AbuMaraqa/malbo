<script setup lang="ts">
defineProps<{ text: string }>();
</script>

<template>
    <div data-section="announcement" class="border-b border-line bg-white px-4 py-2.5 text-center text-sm text-(--ink-soft)">{{ text }}</div>
</template>
