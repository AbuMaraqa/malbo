<script setup lang="ts">
import { SwitchRoot, SwitchThumb } from 'reka-ui';

defineProps<{ id?: string; disabled?: boolean; ariaLabel?: string }>();

const model = defineModel<boolean>({ default: false });
</script>

<template>
    <SwitchRoot
        :id="id"
        v-model="model"
        :disabled="disabled"
        :aria-label="ariaLabel"
        class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full bg-gray-200 p-0.5 transition-colors outline-none focus-visible:shadow-focus-ring data-[state=checked]:bg-brand-500 data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50 dark:bg-gray-700"
    >
        <SwitchThumb class="block size-4 rounded-full bg-white shadow-theme-sm transition-transform duration-150 data-[state=checked]:translate-x-4 rtl:data-[state=checked]:-translate-x-4" />
    </SwitchRoot>
</template>
