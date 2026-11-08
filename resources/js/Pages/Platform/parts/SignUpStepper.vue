<script setup lang="ts">
defineProps<{ steps: string[]; current: number }>();
</script>

<template>
    <ol class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
        <li v-for="(step, index) in steps" :key="step" class="flex items-center gap-2" :aria-current="index === current ? 'step' : undefined">
            <span class="flex size-6 items-center justify-center rounded-full text-xs font-semibold" :class="index <= current ? 'bg-store-600 text-white' : 'bg-gray-200 text-gray-600'">{{ index + 1 }}</span>
            <span :class="index === current ? 'font-semibold text-gray-900' : 'text-gray-500'">{{ step }}</span>
        </li>
    </ol>
</template>
