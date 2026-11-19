<script setup lang="ts">
import BrandPetal from './BrandPetal.vue';
import { sectionLead, sectionTitle } from './styles';

/**
 * A section's centered title with the brand book's heading device: a maroon petal (the mark's
 * swoosh) before the words, pointing at them in either direction. A short line may follow.
 */
defineProps<{ title: string; lead?: string | null; id?: string }>();
</script>

<template>
    <div class="text-center">
        <h2 :id="id" :class="sectionTitle" class="inline-flex items-center gap-3">
            <BrandPetal class="h-[0.55em] w-auto shrink-0 text-store rtl:-scale-x-100" />
            <span>{{ title }}</span>
        </h2>
        <p v-if="lead" :class="sectionLead">{{ lead }}</p>
    </div>
</template>
