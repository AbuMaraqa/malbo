<script setup lang="ts">
import { useId } from 'vue';

import { Switch } from '@/tailadmin/ui/switch';

defineProps<{ label?: string; hint?: string; disabled?: boolean; ariaLabel?: string }>();

const model = defineModel<boolean>();
const id = useId();
</script>

<template>
    <div class="flex items-start gap-3">
        <Switch :id="id" v-model="model" :disabled="disabled" :aria-label="ariaLabel" size="sm" class="mt-0.5" />
        <div v-if="label">
            <label :for="id" class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ label }}</label>
            <p v-if="hint" class="text-sm text-gray-500 dark:text-gray-400">{{ hint }}</p>
        </div>
    </div>
</template>
