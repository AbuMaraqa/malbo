<script setup lang="ts">
import BrandSparkle from './BrandSparkle.vue';
import { sectionLead, sectionTitle } from './styles';

/** A home section's centered title, with a short line under it and the MALBO star between two gold rules. */
defineProps<{ title: string; lead?: string | null; id?: string }>();
</script>

<template>
    <div class="text-center">
        <h2 :id="id" :class="sectionTitle">{{ title }}</h2>
        <span aria-hidden="true" class="mt-3 flex items-center justify-center gap-2.5 text-(--gold)">
            <span class="h-px w-10 bg-current"></span>
            <BrandSparkle class="h-4 w-3" />
            <span class="h-px w-10 bg-current"></span>
        </span>
        <p v-if="lead" :class="sectionLead">{{ lead }}</p>
    </div>
</template>
