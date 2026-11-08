<script setup lang="ts">
defineProps<{ text: string; color: string; background: string }>();
</script>

<template>
    <span class="inline-block rounded px-2 py-1 text-sm font-medium" :style="{ color, backgroundColor: background }">{{ text }}</span>
</template>
