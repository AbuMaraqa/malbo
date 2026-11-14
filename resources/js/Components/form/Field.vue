<script setup lang="ts">
defineProps<{
    label?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    id?: string;
}>();
</script>

<template>
    <div data-field class="min-w-0">
        <label v-if="label" :for="id" class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-gray-200">
            {{ label }}<span v-if="required" class="text-danger" aria-hidden="true"> *</span>
        </label>
        <slot />
        <p v-if="error" class="mt-1 text-sm text-danger" role="alert">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-sm text-muted">{{ hint }}</p>
    </div>
</template>
