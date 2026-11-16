<script setup lang="ts">
import BrandSparkle from './BrandSparkle.vue';

/** The line above the header, on maroon with the brand pattern, between two gold stars. */
defineProps<{ text: string }>();
</script>

<template>
    <div data-section="announcement" class="flex items-center justify-center gap-3 bg-(--night) bg-brand-pattern px-4 py-2.5 text-center text-[13px] font-medium tracking-wide text-white/90">
        <BrandSparkle class="h-3.5 w-2.5 shrink-0 text-(--gold)" />
        <span>{{ text }}</span>
        <BrandSparkle class="h-3.5 w-2.5 shrink-0 text-(--gold)" />
    </div>
</template>
