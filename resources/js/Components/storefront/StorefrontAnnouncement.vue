<script setup lang="ts">
defineProps<{ text: string }>();
</script>

<template>
    <div data-section="announcement" class="bg-(--night) px-4 py-2.5 text-center text-[13px] font-medium tracking-wide text-white/90">{{ text }}</div>
</template>
