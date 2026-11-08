<script setup lang="ts">
/** What a widget shows when there is nothing to count yet: never an empty chart. */
defineProps<{ message: string }>();
</script>

<template>
    <p class="flex min-h-40 items-center justify-center rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
        {{ message }}
    </p>
</template>
