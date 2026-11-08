<script setup lang="ts">
import { computed } from 'vue';

/** Where an image would be: the name's first letter, in the store's color. */
const props = defineProps<{ name: string }>();

const letter = computed(() => Array.from(props.name)[0] ?? '');
</script>

<template>
    <span aria-hidden="true" class="grid size-full place-items-center font-bold text-store/25">{{ letter }}</span>
</template>
