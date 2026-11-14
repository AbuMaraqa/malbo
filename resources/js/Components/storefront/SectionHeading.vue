<script setup lang="ts">
import { sectionLead, sectionTitle } from './styles';

/** A home section's centered title, with a short line under it and a little bar in the store's color. */
defineProps<{ title: string; lead?: string | null; id?: string }>();
</script>

<template>
    <div class="text-center">
        <h2 :id="id" :class="sectionTitle">{{ title }}</h2>
        <span aria-hidden="true" class="mx-auto mt-3 block h-0.5 w-12 rounded-full bg-store"></span>
        <p v-if="lead" :class="sectionLead">{{ lead }}</p>
    </div>
</template>
